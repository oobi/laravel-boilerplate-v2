<?php

namespace Tests\Unit\Theme;

use App\Support\Theme\DaisyColor;
use PHPUnit\Framework\TestCase;

class DaisyColorTest extends TestCase
{
    public function test_it_maps_filament_names_to_daisyui_names(): void
    {
        $this->assertSame('error', DaisyColor::map('danger'));
        $this->assertSame('neutral', DaisyColor::map('gray'));
    }

    public function test_it_passes_through_shared_names_unchanged(): void
    {
        foreach (['primary', 'secondary', 'accent', 'neutral', 'info', 'success', 'warning', 'error'] as $color) {
            $this->assertSame($color, DaisyColor::map($color));
        }
    }
}
