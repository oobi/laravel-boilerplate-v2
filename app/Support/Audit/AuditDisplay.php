<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditEntry;
use App\Support\Theme\DaisyColor;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * How the audit screens show what the log stores. A project names its own
 * categories, events, actor types and subjects from a provider's boot(), as core
 * and the teams tier do, so it never edits core's lang/en/audit.php.
 */
final class AuditDisplay
{
    /** @var array<string, array{label: string, color: DaisyColor, icon: string|null}> */
    private static array $actors = [];

    /** @var array<class-string<Model>, Closure(Model): array{label: string, url?: string|null}> */
    private static array $subjects = [];

    /** @var array<string, string> */
    private static array $categories = [];

    /** @var array<string, list<AuditView>> */
    private static array $categoryViews = [];

    /** @var array<string, array{label: string, sentence: string|null}> */
    private static array $events = [];

    /** An actor type's name, colour and icon (a heroicon component name; a cog by default). */
    public static function registerActor(string $type, string $label, DaisyColor $color = DaisyColor::NEUTRAL, ?string $icon = null): void
    {
        self::$actors[$type] = ['label' => $label, 'color' => $color, 'icon' => $icon];
    }

    /**
     * How to name and link a kind of subject. Only give a URL the viewer may open.
     *
     * @param  class-string<Model>  $model
     * @param  Closure(Model): array{label: string, url?: string|null}  $resolver
     */
    public static function registerSubject(string $model, Closure $resolver): void
    {
        self::$subjects[$model] = $resolver;
    }

    /**
     * A category's name on the screens, for one a project logs with its own
     * string (`Audit::record(..., category: 'billing')`), and the quick views it
     * shows under besides All.
     *
     * @param  list<AuditView>  $views
     */
    public static function registerCategory(BackedEnum|string $category, string $label, array $views = []): void
    {
        $category = $category instanceof BackedEnum ? (string) $category->value : $category;

        self::$categories[$category] = $label;
        self::$categoryViews[$category] = $views;
    }

    /**
     * The project categories registered into a quick view.
     *
     * @return list<string>
     */
    public static function categoriesInView(AuditView $view): array
    {
        return array_keys(array_filter(self::$categoryViews, fn (array $views): bool => in_array($view, $views, true)));
    }

    /**
     * An event in words ("Invoice sent"), and optionally the detail page's
     * sentence, with `:actor` and `:subject` (":actor sent :subject an invoice").
     * Registered before the event is first logged, since an entry's description
     * is stored when it's written.
     */
    public static function registerEvent(string $event, string $label, ?string $sentence = null): void
    {
        self::$events[$event] = ['label' => $label, 'sentence' => $sentence];
    }

    /** The registered words for an event, if a project gave any. */
    public static function eventLabel(string $event): ?string
    {
        return self::$events[$event]['label'] ?? null;
    }

    public static function flush(): void
    {
        self::$actors = [];
        self::$subjects = [];
        self::$categories = [];
        self::$categoryViews = [];
        self::$events = [];
    }

    public static function actorLabel(string $type): string
    {
        return self::$actors[$type]['label'] ?? self::fromLang("audit.actors.{$type}", $type);
    }

    public static function actorColor(string $type): DaisyColor
    {
        return self::$actors[$type]['color'] ?? match ($type) {
            AuditActor::USER => DaisyColor::INFO,
            AuditActor::GUEST => DaisyColor::WARNING,
            default => DaisyColor::NEUTRAL,
        };
    }

    /** The tile shown for an actor with no face: the system, a visitor, a queued job. */
    public static function actorIcon(string $type): string
    {
        return self::$actors[$type]['icon'] ?? match ($type) {
            AuditActor::GUEST => 'heroicon-o-globe-alt',
            AuditActor::QUEUE => 'heroicon-o-queue-list',
            AuditActor::SCHEDULER => 'heroicon-o-clock',
            default => 'heroicon-o-cog-6-tooth',
        };
    }

    public static function categoryLabel(string $category): string
    {
        return self::$categories[$category] ?? self::fromLang("audit.categories.{$category}", $category);
    }

    /**
     * The entry's subject, by name and link. One that's been permanently deleted
     * still says what it was.
     *
     * @return array{label: string, url: string|null}|null
     */
    public static function subject(AuditEntry $entry): ?array
    {
        if ($entry->subject_type === null) {
            return null;
        }

        $subject = $entry->subject;
        $resolver = $subject instanceof Model ? self::$subjects[$subject::class] ?? null : null;

        if ($resolver !== null) {
            return ['url' => null, ...$resolver($subject)];
        }

        $class = Relation::getMorphedModel($entry->subject_type) ?? $entry->subject_type;

        return ['label' => trim(Str::headline(class_basename($class)).' '.$entry->subject_id), 'url' => null];
    }

    /** A stored value as text. Always escaped by the view that shows it. */
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null => __('audit.empty_value'),
            is_bool($value) => $value ? __('audit.true') : __('audit.false'),
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            default => (string) $value,
        };
    }

    /**
     * What happened, under the entry's heading. A description written for the
     * entry comes first ("Deactivated after 5 failed logins"). Otherwise a
     * sentence ("Super Admin updated Michel Adams"): registered with the event,
     * from lang `sentences`, or from `verbs` for a model's own events. Otherwise
     * nothing.
     */
    public static function summary(AuditEntry $entry): ?string
    {
        if (self::hasWrittenDescription($entry)) {
            return $entry->description;
        }

        $sentence = self::sentence($entry);

        if ($sentence === null) {
            return null;
        }

        return strtr($sentence, [
            ':actor' => self::actorName($entry),
            ':subject' => (string) (self::subject($entry)['label'] ?? ''),
        ]);
    }

    /**
     * The summary as HTML for the list, the actor and subject linked where the
     * viewer may open them; null when there is no sentence for the entry.
     */
    public static function summaryHtml(AuditEntry $entry, ?string $actorUrl = null): ?HtmlString
    {
        if (self::hasWrittenDescription($entry)) {
            return new HtmlString(e($entry->description));
        }

        $sentence = self::sentence($entry);

        if ($sentence === null) {
            return null;
        }

        $subject = self::subject($entry);

        // Escape the sentence first, then put the names in, each escaped once.
        return new HtmlString(strtr(e($sentence), [
            ':actor' => self::link(self::actorName($entry), $actorUrl, 'font-medium'),
            ':subject' => self::link((string) ($subject['label'] ?? ''), $subject['url'] ?? null),
        ]));
    }

    /** "By Super Admin", linked as summaryHtml() links the actor: for an entry with no sentence. */
    public static function byHtml(AuditEntry $entry, ?string $actorUrl = null): HtmlString
    {
        return new HtmlString(strtr(e(__('audit.by', ['actor' => ':actor'])), [
            ':actor' => self::link(self::actorName($entry), $actorUrl),
        ]));
    }

    /**
     * Whether the summary names the actor or the subject (`:actor`, `:subject`),
     * so the list needn't say it again. A written description names neither.
     */
    public static function summaryNames(AuditEntry $entry, string $placeholder): bool
    {
        return ! self::hasWrittenDescription($entry) && str_contains((string) self::sentence($entry), $placeholder);
    }

    /** Who did it, by name, or the kind of actor ("System", "Visitor"). */
    public static function actorName(AuditEntry $entry): string
    {
        return $entry->actor_name ?: self::actorLabel($entry->actor_type);
    }

    /**
     * The entry's sentence with its placeholders, when one applies: registered
     * with the event, from lang `sentences`, or from `verbs` for a model's own
     * events. One that names a subject the entry doesn't have doesn't apply.
     */
    private static function sentence(AuditEntry $entry): ?string
    {
        $event = (string) $entry->event;
        $key = str_replace('.', '_', $event);
        $verb = Str::afterLast($event, '.');

        $sentence = match (true) {
            isset(self::$events[$event]['sentence']) => self::$events[$event]['sentence'],
            Lang::has("audit.sentences.{$key}") => __("audit.sentences.{$key}"),
            Lang::has("audit.verbs.{$verb}") => ':actor '.__("audit.verbs.{$verb}").' :subject',
            default => null,
        };

        return $sentence !== null && (self::subject($entry) !== null || ! str_contains($sentence, ':subject')) ? $sentence : null;
    }

    private static function link(string $text, ?string $url, string $class = ''): string
    {
        return $url === null
            ? ($class === '' ? e($text) : '<span class="'.$class.'">'.e($text).'</span>')
            : '<a href="'.e($url).'" class="'.trim('link link-hover '.$class).'">'.e($text).'</a>';
    }

    /**
     * Whether someone wrote the description, rather than it being the event in
     * words: as it reads now, or as the bare event code tidied. An event's wording
     * that changes later still leaves its older entries with a description that
     * reads as written; see docs/boilerplate/audit-log.md.
     */
    public static function hasWrittenDescription(AuditEntry $entry): bool
    {
        $description = (string) $entry->description;

        // With no event, the description is already the entry's heading.
        if ($description === '' || $entry->event === null) {
            return false;
        }

        return ! in_array($description, [Audit::describe($entry->event), Audit::tidy($entry->event)], true);
    }

    /** An attribute or property name in words: `email` reads "Email address". */
    public static function fieldLabel(string $field): string
    {
        $key = "audit.fields.{$field}";

        return Lang::has($key) ? __($key) : Str::ucfirst(str_replace('_', ' ', Str::snake($field)));
    }

    private static function fromLang(string $key, string $fallback): string
    {
        return Lang::has($key) ? __($key) : Str::headline($fallback);
    }
}
