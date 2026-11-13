<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTimeZone;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The zone people read times in, apart from the zone the app stores and works
 * in (`app.timezone`, UTC). Core resolves it from the organisation's setting
 * (AppServiceProvider); without a resolver it reads `APP_DISPLAY_TIMEZONE`.
 * Used by the audit screens; other screens can adopt it.
 */
final class DisplayTimezone
{
    /** @var (Closure(): ?string)|null */
    private static ?Closure $resolver = null;

    /**
     * Where the zone comes from, asked each time a time is shown, so nothing
     * reads it while installing or migrating. Null goes back to the config.
     * An add-on can wrap it (a team's zone, falling back to resolver()'s).
     *
     * @param  (Closure(): ?string)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /** @return (Closure(): ?string)|null */
    public static function resolver(): ?Closure
    {
        return self::$resolver;
    }

    /** The zone's identifier ("Australia/Brisbane"), the app's own when none is set. */
    public static function name(): string
    {
        $zone = (self::$resolver !== null ? (self::$resolver)() : config('app.display_timezone')) ?: config('app.timezone');

        // A bad value would otherwise show every time in the wrong zone, or fail on the first one.
        if (! self::isValid($zone)) {
            throw new InvalidArgumentException("The display time zone is not a time zone: [{$zone}].");
        }

        return $zone;
    }

    /** Whether PHP knows the zone, including the older aliases ("US/Eastern"). */
    public static function isValid(mixed $zone): bool
    {
        return is_string($zone) && in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
    }

    /** The moment in the display zone. */
    public static function of(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(self::name());
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::name());
    }

    /**
     * The zone as people say it, at a moment (daylight saving changes the
     * abbreviation): "Brisbane time (AEST)", "UTC".
     */
    public static function label(?CarbonInterface $at = null): string
    {
        $zone = self::name();

        if (in_array($zone, ['UTC', 'Etc/UTC', 'Universal', 'Zulu'], true)) {
            return 'UTC';
        }

        return __('admin.time_zone_label', [
            'place' => str_replace('_', ' ', Str::afterLast($zone, '/')),
            'abbreviation' => self::abbreviation($at ?? CarbonImmutable::now()),
        ]);
    }

    /** "AEST", or "UTC+3" for a zone with no lettered abbreviation. */
    public static function abbreviation(CarbonInterface $at): string
    {
        $local = self::of($at);
        $abbreviation = $local->format('T');

        if (! preg_match('/^[+-]/', $abbreviation)) {
            return $abbreviation;
        }

        $offset = $local->format('P');

        return 'UTC'.preg_replace(['/:00$/', '/^([+-])0/'], ['', '$1'], $offset);
    }
}
