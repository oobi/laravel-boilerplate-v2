<?php

namespace App\Support\Theme;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The app's shared semantic color palette (daisyUI's 8 names), the single
 * source of truth instead of magic strings scattered across HasColor enums,
 * Filament ->color() calls, and x-* Blade components.
 *
 * Also the boundary between Filament's palette (danger/gray, plus
 * primary/info/success/warning which pass straight through) and daisyUI's
 * names (error/neutral/…) — see toFilamentColor()/fromFilamentColor().
 *
 * Implements Filament's HasLabel/HasColor so ->enum(DaisyColor::class) on a
 * Select/ToggleButtons field auto-derives labelled, colored swatch options
 * (see HasColors::getColors()) without a per-call-site options() closure.
 */
enum DaisyColor: string implements HasColor, HasLabel
{
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
    case ACCENT = 'accent';
    case NEUTRAL = 'neutral';
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::PRIMARY => __('admin.color_primary'),
            self::SECONDARY => __('admin.color_secondary'),
            self::ACCENT => __('admin.color_accent'),
            self::NEUTRAL => __('admin.color_neutral'),
            self::INFO => __('admin.color_info'),
            self::SUCCESS => __('admin.color_success'),
            self::WARNING => __('admin.color_warning'),
            self::ERROR => __('admin.color_error'),
        };
    }

    public function getColor(): string
    {
        return $this->toFilamentColor();
    }

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
