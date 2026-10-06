<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Filament\AdminAction;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Tests\TestCase;

class AdminActionTest extends TestCase
{
    public function test_it_defaults_to_a_small_soft_button(): void
    {
        $action = AdminAction::make('demo');

        $this->assertFalse($action->isOutlined());
        $this->assertSame(Size::Small, $action->getSize());
        $this->assertSame('fi-btn-soft', $action->getExtraAttributes()['class'] ?? null);
    }

    public function test_style_for_list_gives_a_plain_action_the_same_look(): void
    {
        $action = AdminAction::styleForList(Action::make('demo')->extraAttributes(['class' => 'existing']));

        $this->assertSame(Size::Small, $action->getSize());
        $this->assertEqualsCanonicalizing(['existing', 'fi-btn-soft'], explode(' ', $action->getExtraAttributes()['class'] ?? ''));
    }
}
