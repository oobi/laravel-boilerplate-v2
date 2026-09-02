<?php

namespace App\Support\Theme;

/**
 * The app's shared semantic color palette (daisyUI's 8 names), the single
 * source of truth instead of magic strings scattered across HasColor enums,
 * Filament ->color() calls, and x-* Blade components.
 *
 * Also the boundary between Filament's palette (danger/gray, plus
 * primary/info/success/warning which pass straight through) and daisyUI's
 * names (error/neutral/…) — see toFilamentColor()/fromFilamentColor().
 */
enum DaisyColor: string
{
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
    case ACCENT = 'accent';
    case NEUTRAL = 'neutral';
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';

    /** Filament's palette name for this color (only error/neutral differ: danger/gray). */
    public function toFilamentColor(): string
    {
        return match ($this) {
            self::ERROR => 'danger',
            self::NEUTRAL => 'gray',
            default => $this->value,
        };
    }

    /** Resolves either a Filament palette name (danger/gray/…) or a daisyUI name to its shared case. */
    public static function fromFilamentColor(string $color): self
    {
        return match ($color) {
            'danger' => self::ERROR,
            'gray' => self::NEUTRAL,
            default => self::from($color),
        };
    }
}
