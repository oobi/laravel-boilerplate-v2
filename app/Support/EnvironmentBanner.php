<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The strip across the top of every page naming a non-production
 * environment (config('app.environment_banner')). Off while its text is
 * unset. The colour comes from .env, so it is validated before it reaches a
 * style attribute, and the text colour is picked to stay readable on it.
 */
final class EnvironmentBanner
{
    public const string DEFAULT_COLOR = '#facc15';

    public static function text(): ?string
    {
        $text = trim((string) config('app.environment_banner.text'));

        return $text === '' ? null : $text;
    }

    public static function isShown(): bool
    {
        return self::text() !== null;
    }

    /**
     * The configured background as #rrggbb, or the default when it is not a
     * #rgb / #rrggbb hex value.
     */
    public static function color(): string
    {
        $color = trim((string) config('app.environment_banner.color'));

        if (! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            return self::DEFAULT_COLOR;
        }

        if (strlen($color) === 4) {
            $color = '#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3];
        }

        return strtolower($color);
    }

    /**
     * Black or white, whichever has the higher WCAG contrast ratio on the
     * background colour.
     */
    public static function contentColor(): string
    {
        $luminance = self::relativeLuminance(self::color());

        $onBlack = ($luminance + 0.05) / 0.05;
        $onWhite = 1.05 / ($luminance + 0.05);

        return $onBlack >= $onWhite ? '#000000' : '#ffffff';
    }

    /**
     * WCAG 2 relative luminance of a #rrggbb colour.
     */
    private static function relativeLuminance(string $hex): float
    {
        [$red, $green, $blue] = collect(str_split(substr($hex, 1), 2))
            ->map(function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            })
            ->all();

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
