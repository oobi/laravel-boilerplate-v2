<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The zone people read times in (`APP_DISPLAY_TIMEZONE`), apart from the zone
 * the app stores and works in (`app.timezone`, UTC). Used by the audit screens;
 * other screens can adopt it.
 */
final class DisplayTimezone
{
    /** The zone's identifier ("Australia/Brisbane"), the app's own when none is set. */
    public static function name(): string
    {
        $zone = config('app.display_timezone') ?: config('app.timezone');

        // A typo would otherwise show every time in the wrong zone, or fail on the first one.
        if (! in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException("APP_DISPLAY_TIMEZONE is not a time zone: [{$zone}].");
        }

        return $zone;
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
