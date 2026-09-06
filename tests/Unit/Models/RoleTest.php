<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Role;
use App\Support\Theme\DaisyColor;
use Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_badge_color_falls_back_to_neutral_when_unset(): void
    {
        $role = new Role;

        $this->assertSame(DaisyColor::NEUTRAL, $role->badgeColor());
    }

    public function test_badge_color_returns_the_stored_color(): void
    {
        $role = new Role;
        $role->color = DaisyColor::WARNING;

        $this->assertSame(DaisyColor::WARNING, $role->badgeColor());
    }
}
