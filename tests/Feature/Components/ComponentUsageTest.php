<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Views use the Blade components (resources/views/components, listed in the
 * design-system skill), never hand-rolled copies of them: raw daisyUI
 * component classes, or status strips built from a coloured border or tint.
 * See .ai/rules/views.md. A deliberate exception goes in ALLOWED with why.
 */
class ComponentUsageTest extends TestCase
{
    /**
     * What's hand-rolled, and the component to use instead. Whole class names
     * only, so card-inset, btn-surface and the like don't count.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        '/class="[^"]*(?<![\w-])btn(?![\w-])/' => '<x-button.*> (action, secondary, cancel, back, danger, warning, icon)',
        '/class="[^"]*(?<![\w-])alert(?![\w-])/' => '<x-alert>',
        '/class="[^"]*(?<![\w-])card(?![\w-])/' => '<x-card> (inset when nested)',
        '/class="[^"]*(?<![\w-])badge(?![\w-])/' => '<x-badge>',
        '/class="[^"]*(?<![\w-])ui-banner(?![\w-])/' => '<x-banner>',
        '/border-l-4 border-(info|success|warning|error)\b/' => '<x-banner> or <x-alert>',
        '/(?<![\w:-])bg-(info|success|warning|error)\/\d+/' => '<x-banner> or <x-alert>',
    ];

    /**
     * Deliberate exceptions: by view path, the components it may hand-roll
     * (their names, e.g. <x-badge>), each with why.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        // A file input needs a <label> to open the picker; no button component renders one.
        'resources/views/livewire/profile/edit-profile.blade.php' => ['<x-button'],
        // Clickable colour swatches, a picker built on badge styles, not a badge.
        'resources/views/filament/forms/components/role-color-swatches.blade.php' => ['<x-badge>'],
        // The gallery: a joined button group (daisyUI join, no component yet) and the
        // needs-attention card's tinted icon tile (an icon backdrop, not a strip).
        'packages/theme-demo/resources/views/livewire/component-gallery.blade.php' => ['<x-button', '<x-banner>'],
        // Pagination as a joined button group (daisyUI join, no component yet).
        'packages/theme-demo/resources/views/livewire/tables/maximalist.blade.php' => ['<x-button'],
    ];

    public function test_views_use_the_components_rather_than_hand_rolled_markup(): void
    {
        $found = collect([resource_path('views'), ...glob(base_path('packages/*/resources/views'))])
            ->flatMap(fn (string $directory): array => File::allFiles($directory))
            ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php')
                && ! str_starts_with($file->getPathname(), resource_path('views/components')))
            ->flatMap(function (SplFileInfo $file): array {
                $path = str_replace(base_path().'/', '', $file->getPathname());

                return collect(self::hits($file->getContents()))
                    ->reject(fn (string $component): bool => collect(self::ALLOWED[$path] ?? [])->contains(fn (string $allowed): bool => str_starts_with($component, $allowed)))
                    ->map(fn (string $component): string => "{$path}: use {$component}")
                    ->values()
                    ->all();
            })
            ->values();

        $this->assertSame([], $found->all(), "Hand-rolled markup where a component exists (see .ai/rules/views.md):\n".$found->implode("\n"));
    }

    public function test_the_check_catches_hand_rolled_markup_and_spares_lookalikes(): void
    {
        foreach ([
            '<a class="btn btn-primary">' => '<x-button',
            '<div role="alert" class="alert alert-warning">' => '<x-alert>',
            '<div class="card bg-base-100">' => '<x-card>',
            '<span class="badge badge-soft">' => '<x-badge>',
            '<div class="flex rounded-box border-l-4 border-warning px-4">' => '<x-banner>',
            '<div class="rounded-box bg-success/8 p-4">' => '<x-banner>',
        ] as $markup => $component) {
            $this->assertNotSame([], array_filter(self::hits($markup), fn (string $hit): bool => str_starts_with($hit, $component)), $markup);
        }

        foreach ([
            '<div class="card-inset rounded-box">',
            '<x-button class="btn-surface">',
            '<label class="has-checked:bg-primary/8 hover:bg-warning/10">',
            '<x-card class="card-title">',
        ] as $markup) {
            $this->assertSame([], self::hits($markup), $markup);
        }
    }

    /**
     * The components hand-rolled in this markup.
     *
     * @return list<string>
     */
    private static function hits(string $contents): array
    {
        return collect(self::PATTERNS)
            ->filter(fn (string $component, string $pattern): bool => preg_match($pattern, $contents) === 1)
            ->values()
            ->all();
    }
}
