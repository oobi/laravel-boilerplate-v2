<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Lab404\Impersonate\Services\ImpersonateManager;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Enums\ActivityEvent;
use Stringable;

/**
 * Every write to the audit log passes through here, whichever way it started:
 * the Auditable trait, Audit::record() or a direct activity() call. So this is
 * the one place that names the actor and removes what must never be stored.
 */
class LogAuditEntry extends LogActivityAction
{
    /** Stored instead of a sensitive value, so a change still shows without revealing it. */
    public const string REDACTED = '[redacted]';

    public function execute(Model $activity, string $description): Model
    {
        $description = $this->nameModelEvent($activity, $description);

        $this->recordActor($activity);
        $this->recordOrigin($activity);

        return parent::execute($activity, $description);
    }

    /**
     * spatie would expand `:causer.password` in a description with the real value,
     * and descriptions can carry user-entered text. Never expand anything.
     */
    protected function resolveDescription(Model $activity, string $description): string
    {
        return Str::limit(mb_scrub($activity->description ?? $description), config()->integer('audit.max_value_length'));
    }

    protected function transformChanges(Model $activity): void
    {
        $subject = $activity->getRelationValue('subject');
        $changes = collect($activity->attribute_changes ?? []);
        $hiddenAttributes = $this->sensitiveAttributesOf($subject);
        $sensitiveAttributes = [...$hiddenAttributes, ...$this->attributesOutsideVisible($subject, $changes)];

        $activity->attribute_changes = $changes->map(fn (mixed $values, int|string $key): mixed => in_array($key, ['attributes', 'old'], true) && is_array($values)
            ? $this->sanitize($values, $sensitiveAttributes)
            : $this->sanitize([$key => $values], $sensitiveAttributes)[$key]);

        $activity->properties = collect($this->sanitize(collect($activity->properties ?? [])->all(), $hiddenAttributes));
    }

    /** A model's `updated` is stored as `user.updated`, and described as "User updated" unless it says otherwise. */
    private function nameModelEvent(Model $activity, string $description): string
    {
        $subject = $activity->getRelationValue('subject');

        if (! $subject instanceof Model || ActivityEvent::tryFrom((string) $activity->getAttribute('event')) === null) {
            return $description;
        }

        $isDefaultDescription = $description === $activity->getAttribute('event');
        $isForceDelete = $activity->getAttribute('event') === ActivityEvent::Deleted->value
            && method_exists($subject, 'isForceDeleting')
            && $subject->isForceDeleting();

        $event = Str::snake(class_basename($subject)).'.'.($isForceDelete ? 'force_deleted' : $activity->getAttribute('event'));

        $activity->setAttribute('event', $event);

        return $isDefaultDescription ? Audit::describe($event) : $description;
    }

    private function recordActor(Model $activity): void
    {
        $causer = $activity->getRelationValue('causer');
        $isSignedIn = $causer instanceof Model;

        // The sync queue driver leaves its Context behind in the request that ran the job.
        $dispatchedBy = $isSignedIn || $this->isWebRequest() ? null : Context::getHidden(AuditActor::CONTEXT_DISPATCHED_BY);

        if ($dispatchedBy !== null && ($causer = User::withTrashed()->find($dispatchedBy)) !== null) {
            $activity->causer()->associate($causer);
        }

        $activity->setAttribute('actor_type', Context::getHidden(AuditActor::CONTEXT_TYPE) ?? match (true) {
            $isSignedIn => AuditActor::USER,
            $dispatchedBy !== null => AuditActor::QUEUE,
            $this->isWebRequest() => AuditActor::GUEST,
            Context::hasHidden(AuditActor::CONTEXT_IN_JOB) => AuditActor::QUEUE,
            default => AuditActor::SYSTEM,
        });

        $activity->setAttribute('actor_name', $this->snapshotName(Context::getHidden(AuditActor::CONTEXT_NAME) ?? $causer?->getAttribute('name')));
    }

    /** Where it came from. A job has no IP or browser, but still knows who was impersonating when it was dispatched. */
    private function recordOrigin(Model $activity): void
    {
        if ($this->isWebRequest()) {
            $activity->setAttribute('ip_address', request()->ip());
            $activity->setAttribute('user_agent', Str::limit(mb_scrub((string) request()->userAgent()), 512, '') ?: null);
        }

        $impersonatorId = $this->isWebRequest()
            ? app(ImpersonateManager::class)->getImpersonatorId()
            : Context::getHidden(AuditActor::CONTEXT_IMPERSONATED_BY);

        $impersonator = $impersonatorId === null ? null : User::withTrashed()->find($impersonatorId);

        if ($impersonator !== null) {
            $activity->setAttribute('impersonator_id', $impersonator->getKey());
            $activity->setAttribute('impersonator_name', $this->snapshotName($impersonator->name));
        }
    }

    /** A person's name can outgrow the column, and that must never stop them from acting. */
    private function snapshotName(mixed $name): ?string
    {
        return is_string($name) || $name instanceof Stringable ? Str::limit((string) $name, 255, '') : null;
    }

    /** A console command, job or test has a request object too, but only a real request has a route. */
    private function isWebRequest(): bool
    {
        return app()->bound('request') && request()->route() !== null;
    }

    /**
     * A subject's own sensitive attributes: what it hides from serialization, even
     * if this instance has made it visible, and whatever it encrypts or hashes.
     *
     * @return list<string>
     */
    private function sensitiveAttributesOf(mixed $subject): array
    {
        if (! $subject instanceof Model) {
            return [];
        }

        $protectedByCast = collect($subject->getCasts())
            ->filter(fn (string $cast): bool => Str::contains($cast, ['encrypted', 'hashed'], ignoreCase: true))
            ->keys()
            ->all();

        return [...$subject->getHidden(), ...$subject->newInstance()->getHidden(), ...$protectedByCast];
    }

    /**
     * A model that lists `$visible` hides everything else, so the same goes here.
     *
     * @param  Collection<array-key, mixed>  $changes
     * @return list<string>
     */
    private function attributesOutsideVisible(mixed $subject, Collection $changes): array
    {
        $visible = $subject instanceof Model ? $subject->newInstance()->getVisible() : [];

        if ($visible === []) {
            return [];
        }

        $changed = $changes->only(['attributes', 'old'])
            ->filter(fn (mixed $values): bool => is_array($values))
            ->flatMap(fn (array $values): array => array_keys($values))
            ->all();

        return array_values(array_diff($changed, $visible));
    }

    /**
     * Redact sensitive names at any depth, and cut long strings. A null stays null,
     * so "set" and "cleared" stay distinguishable without revealing anything.
     *
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $sensitiveAttributes
     * @return array<array-key, mixed>
     */
    private function sanitize(array $values, array $sensitiveAttributes = []): array
    {
        return collect($values)->map(function (mixed $value, int|string $key) use ($sensitiveAttributes): mixed {
            if ($value !== null && $this->isSensitive((string) $key, $sensitiveAttributes)) {
                return self::REDACTED;
            }

            return match (true) {
                is_array($value) => $this->sanitize($value, $sensitiveAttributes),
                is_string($value) => Str::limit($value, config()->integer('audit.max_value_length')),
                default => $value,
            };
        })->all();
    }

    /** @param  list<string>  $sensitiveAttributes */
    private function isSensitive(string $name, array $sensitiveAttributes): bool
    {
        $patterns = array_map($this->normalizeName(...), [...config('audit.redact'), ...$sensitiveAttributes]);

        return Str::is($patterns, $this->normalizeName($name));
    }

    /** So `apiKey`, `api_key` and `X-Api-Key` all match the same pattern. */
    private function normalizeName(string $name): string
    {
        return Str::lower(str_replace(['_', '-', '.', ' '], '', $name));
    }
}
