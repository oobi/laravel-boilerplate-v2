<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditEntry;
use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes to the audit log. A model's own changes need only the Auditable trait;
 * this is for everything else. Who and where are filled in by LogAuditEntry.
 */
final class Audit
{
    /**
     * Record that something happened. A failure throws, so an entry written in a
     * transaction commits or rolls back with the change it describes.
     *
     * @param  string  $event  dotted and past tense, e.g. `invoice.sent`
     * @param  array<string, mixed>  $properties  anything else worth keeping; sensitive names, and a model's own sensitive attributes, are redacted
     * @param  string|null  $description  defaults to the event in words ("Invoice sent")
     * @param  Model|null  $by  the causer, when it isn't the signed-in user
     * @param  DateTimeInterface|null  $at  backdate an entry, for an import
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        array $properties = [],
        ?string $description = null,
        BackedEnum|string $category = AuditCategory::DATA,
        ?Model $by = null,
        ?DateTimeInterface $at = null,
    ): void {
        activity()
            ->useLog($category)
            ->event($event)
            ->withProperties($properties)
            ->when($subject !== null, fn ($logger) => $logger->performedOn($subject))
            ->when($by !== null, fn ($logger) => $logger->causedBy($by))
            ->when($at !== null, fn ($logger) => $logger->createdAt($at))
            ->log($description ?? self::describe($event));
    }

    /**
     * Record values that changed, shown as the entry's Changes table, for
     * values the caller has vetted itself: settings, whose secrets are marked
     * in code (encrypted, or shown with a SecretInput). So no value is
     * redacted for its name, as properties are; pass a secret's value as
     * LogAuditEntry::REDACTED. Long strings are still cut.
     *
     * @param  string  $event  dotted and past tense, e.g. `settings.updated`
     * @param  array<string, array{label: string, before: mixed, after: mixed}>  $changes  keyed by field
     */
    public static function recordChanges(
        string $event,
        array $changes,
        BackedEnum|string $category = AuditCategory::DATA,
        ?Model $by = null,
    ): void {
        $changes = collect($changes);

        activity()
            ->useLog($category)
            ->event($event)
            ->withChanges([
                'old' => $changes->map(fn (array $change): mixed => $change['before'])->all(),
                'attributes' => $changes->map(fn (array $change): mixed => $change['after'])->all(),
                'labels' => $changes->map(fn (array $change): string => $change['label'])->all(),
            ])
            ->when($by !== null, fn ($logger) => $logger->causedBy($by))
            ->tap(function (AuditEntry $entry): void {
                $entry->changesVetted = true;
            })
            ->log(self::describe($event));
    }

    /**
     * Record something that must carry on even if the audit log can't: a login, a
     * logout, a scheduled task. A failure is reported instead of thrown. The
     * transaction is a savepoint inside any surrounding one, which Postgres would
     * otherwise abort when the failed write is caught.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function attempt(
        string $event,
        ?Model $subject = null,
        array $properties = [],
        BackedEnum|string $category = AuditCategory::DATA,
        ?Model $by = null,
    ): void {
        try {
            (new AuditEntry)->getConnection()->transaction(
                fn () => self::record($event, $subject, $properties, category: $category, by: $by),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Run a block as an actor that isn't a person: an import, an integration.
     * Reaches any job the block dispatches too.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function as(string $actorType, Closure $callback, ?string $actorName = null): mixed
    {
        return Context::scope($callback, hidden: [
            AuditActor::CONTEXT_TYPE => $actorType,
            AuditActor::CONTEXT_NAME => $actorName,
        ]);
    }

    /**
     * Run a block without logging, for bulk work that would flood the log.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function withoutLogging(Closure $callback): mixed
    {
        return activity()->withoutLogging($callback);
    }

    /**
     * An event in words: `invoice.sent` reads "Invoice sent". Core's own events
     * are written out in lang/en/audit.php (`events`); a project's are registered
     * with AuditDisplay::registerEvent().
     */
    public static function describe(string $event): string
    {
        if (($registered = AuditDisplay::eventLabel($event)) !== null) {
            return $registered;
        }

        $key = 'audit.events.'.str_replace('.', '_', $event);

        if (Lang::has($key)) {
            return __($key);
        }

        return self::tidy($event);
    }

    /** The event code itself as words, with no wording given for it. */
    public static function tidy(string $event): string
    {
        return Str::ucfirst(str_replace(' force deleted', ' permanently deleted', str_replace(['.', '_'], ' ', $event)));
    }
}
