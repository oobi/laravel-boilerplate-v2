<?php

declare(strict_types=1);

namespace App\Support\Theme;

use Illuminate\Support\HtmlString;

/**
 * daisyUI's `status` dot, the marker for a state (Active, Suspended,
 * Pending…) shown beside its label: a badge is for a kind of thing (a role),
 * a dot and a word for a state. Shared by <x-status> and the Filament
 * `->status()` column/entry macro so both draw the same dot. The dot is
 * decorative (aria-hidden): the label always carries the meaning.
 */
class StatusDot
{
    public const SIZES = ['xs', 'sm', 'md', 'lg', 'xl'];

    /**
     * @param  string  $color  A daisyUI or Filament colour name (danger, gray… map across).
     * @param  'xs'|'sm'|'md'|'lg'|'xl'|null  $size  daisyUI's status-{size}; null is its default (md).
     * @param  'ping'|'bounce'|null  $animation  As daisyUI's docs show; skipped when the viewer prefers reduced motion.
     */
    public static function html(string $color, ?string $size = null, ?string $animation = null): HtmlString
    {
        $classes = collect([
            'status',
            'status-'.DaisyColor::fromFilamentColor($color)->value,
            in_array($size, self::SIZES, true) ? "status-{$size}" : null,
        ])->filter()->implode(' ');

        // daisyUI's documented markup, animations behind motion-safe:.
        return new HtmlString(match ($animation) {
            'ping' => '<span class="inline-grid *:[grid-area:1/1]" aria-hidden="true">'
                ."<span class=\"{$classes} motion-safe:animate-ping\"></span>"
                ."<span class=\"{$classes}\"></span>"
                .'</span>',
            'bounce' => "<span class=\"{$classes} motion-safe:animate-bounce\" aria-hidden=\"true\"></span>",
            default => "<span class=\"{$classes}\" aria-hidden=\"true\"></span>",
        });
    }
}
