<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditEntry;
use App\Support\Theme\DaisyColor;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * How the audit screens show what the log stores. A project registers its own
 * actor types and subjects from a provider's boot(), as core and the teams tier do.
 */
final class AuditDisplay
{
    /** @var array<string, array{label: string, color: DaisyColor}> */
    private static array $actors = [];

    /** @var array<class-string<Model>, Closure(Model): array{label: string, url?: string|null}> */
    private static array $subjects = [];

    public static function registerActor(string $type, string $label, DaisyColor $color = DaisyColor::NEUTRAL): void
    {
        self::$actors[$type] = ['label' => $label, 'color' => $color];
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

    public static function flush(): void
    {
        self::$actors = [];
        self::$subjects = [];
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

    public static function categoryLabel(string $category): string
    {
        return self::fromLang("audit.categories.{$category}", $category);
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

    private static function fromLang(string $key, string $fallback): string
    {
        return Lang::has($key) ? __($key) : Str::headline($fallback);
    }
}
