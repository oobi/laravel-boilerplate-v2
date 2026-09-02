<?php

namespace App\Support\Theme;

/**
 * Shared color-name boundary between Filament's palette (danger/gray, plus
 * primary/info/success/warning which pass straight through) and daisyUI's
 * semantic colors (error/neutral/…) — used by every x-* component so the
 * mapping only lives in one place.
 */
class DaisyColor
{
    public static function map(string $color): string
    {
        return match ($color) {
            'danger' => 'error',
            'gray' => 'neutral',
            default => $color,
        };
    }
}
