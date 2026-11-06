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

    /**
     * dialog[open] would also match a dialog opened with show(), which is part
     * of the page: below lg that page could no longer scroll (GitHub #86).
     */
    public function test_only_a_modal_dialog_locks_the_page_behind_it(): void
    {
        $css = file_get_contents(resource_path('css/theme/components/ui/layout.css'));

        $this->assertMatchesRegularExpression('/html:has\\([^{]*dialog:modal[^{]*\\)\s*\{\s*overflow: hidden;/', $css);
        $this->assertStringNotContainsString('dialog[open]', $css);
    }

    /**
     * Text fields match :focus-visible on a tap too, so without the pointer check
     * tapping one could scroll it out from under the finger (GitHub #85).
     */
    public function test_the_focus_helper_leaves_presses_and_modals_alone_and_clears_marked_headings(): void
    {
        $script = file_get_contents(resource_path('js/components/keep-focus-clear-of-top-bar.js'));

        $this->assertStringContainsString("addEventListener('pointerdown', () => { focusIsFromPointer = true; }", $script);
        $this->assertStringContainsString("['click', 'pointercancel', 'keydown']", $script);
        $this->assertStringContainsString('() => { focusIsFromPointer = false; }', $script);
        $this->assertStringContainsString('const isReturning = target === lastFocused;', $script);
        $this->assertStringContainsString("if (! isReturning && ! focusIsFromPointer && target.matches?.(KEYBOARD_FOCUS) && ! target.closest('dialog:modal, .fi-modal'))", $script);
        $this->assertStringContainsString("querySelectorAll('[data-keep-focus-clear]')", $script);

        foreach (['time', 'date', 'datetime-local', 'month', 'week'] as $type) {
            $this->assertStringContainsString("[type={$type}]", $script);
        }
    }
}
