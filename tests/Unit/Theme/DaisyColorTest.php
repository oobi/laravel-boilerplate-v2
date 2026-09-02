<?php

namespace Tests\Unit\Theme;

use App\Support\Theme\DaisyColor;
use PHPUnit\Framework\TestCase;

class DaisyColorTest extends TestCase
{
    public function test_it_maps_filament_names_to_daisyui_names(): void
    {
        $this->assertSame(DaisyColor::ERROR, DaisyColor::fromFilamentColor('danger'));
        $this->assertSame(DaisyColor::NEUTRAL, DaisyColor::fromFilamentColor('gray'));
    }

    public function test_it_passes_through_shared_names_unchanged(): void
    {
        foreach (DaisyColor::cases() as $color) {
            $this->assertSame($color, DaisyColor::fromFilamentColor($color->value));
        }
    }

    public function test_it_maps_daisyui_names_back_to_filament_names(): void
    {
        $this->assertSame('danger', DaisyColor::ERROR->toFilamentColor());
        $this->assertSame('gray', DaisyColor::NEUTRAL->toFilamentColor());
    }

    public function test_it_passes_through_shared_filament_names_unchanged(): void
    {
        foreach ([DaisyColor::PRIMARY, DaisyColor::SECONDARY, DaisyColor::ACCENT, DaisyColor::INFO, DaisyColor::SUCCESS, DaisyColor::WARNING] as $color) {
            $this->assertSame($color->value, $color->toFilamentColor());
        }
    }
}
