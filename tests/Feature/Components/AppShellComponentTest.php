<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar drawer behaves as a modal dialog (GitHub #32): the menu buttons
 * say what they open and whether it is open, and the drawer holds focus,
 * makes the page behind inert, and closes on Escape or when any modal opens.
 */
class AppShellComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_drawer_is_a_modal_dialog_the_menu_buttons_control(): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard'))->assertOk();

        $response->assertSeeInOrder(['id="app-drawer"', 'role="dialog"', 'aria-modal="true"', 'aria-label="'.__('Menu').'"', 'x-trap.inert="sidebarDrawerOpen"', '@keydown.escape="sidebarDrawerOpen = false"', '@ui-modal-opened.window="sidebarDrawerOpen = false"', '@open-modal.window="sidebarDrawerOpen = false"'], false);

        $this->assertSame(2, substr_count($response->getContent(), 'aria-controls="app-drawer"'));
        $this->assertSame(2, substr_count($response->getContent(), 'x-bind:aria-expanded="sidebarDrawerOpen"'));
    }
}
