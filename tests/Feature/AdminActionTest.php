<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Filament\AdminAction;
use Filament\Support\Enums\Size;
use Tests\TestCase;

class AdminActionTest extends TestCase
{
    public function test_it_defaults_to_a_small_outlined_button(): void
    {
        $action = AdminAction::make('demo');

        $this->assertTrue($action->isOutlined());
        $this->assertSame(Size::Small, $action->getSize());
    }

    public function test_soft_makes_it_a_soft_button_and_drops_the_outline(): void
    {
        $action = AdminAction::make('demo')->soft();

        $this->assertFalse($action->isOutlined());
        $this->assertSame('fi-btn-soft', $action->getExtraAttributes()['class'] ?? null);
    }

    public function test_soft_can_be_toggled_off(): void
    {
        $action = AdminAction::make('demo')->soft(false);

        // Left as the outlined default — no soft class applied.
        $this->assertTrue($action->isOutlined());
        $this->assertArrayNotHasKey('class', $action->getExtraAttributes());
    }
}
