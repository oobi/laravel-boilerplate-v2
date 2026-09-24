<?php

namespace Tests\Feature\Admin\Users;

use App\Actions\Impersonation\StartImpersonation;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lab404\Impersonate\Services\ImpersonateManager;
use Livewire\Livewire;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    // --- Starting impersonation, from the Show page's CSRF-protected action ---

    public function test_super_admin_can_impersonate_a_regular_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        // Impersonation lands on the target's own post-login destination — a
        // team-less user's is onboarding, not the admin dashboard.
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('impersonate')
            ->assertRedirect(route('team.onboarding'));

        $this->assertAuthenticatedAs($target);
        $this->assertEquals($admin->id, session('impersonated_by'));
    }

    public function test_impersonation_lands_on_the_targets_own_team(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $team = Team::factory()->create();
        $team->addMember($target);

        // You see what they'd see after login: straight into their team.
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('impersonate')
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $this->assertAuthenticatedAs($target);
    }

    public function test_support_can_impersonate_a_regular_user(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        Livewire::actingAs($support)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('impersonate');

        $this->assertAuthenticatedAs($target);
    }

    // --- Write-boundary authorization (the guard behind hiding the button) ---

    public function test_regular_users_cannot_impersonate(): void
    {
        $this->assertImpersonationDenied(User::factory()->create(), User::factory()->create());
    }

    public function test_super_admins_cannot_be_impersonated(): void
    {
        $this->assertImpersonationDenied(
            User::factory()->superAdmin()->create(),
            User::factory()->superAdmin()->create(),
        );
    }

    public function test_inactive_users_cannot_be_impersonated(): void
    {
        $this->assertImpersonationDenied(
            User::factory()->superAdmin()->create(),
            User::factory()->inactive()->create(),
        );
    }

    public function test_a_user_cannot_impersonate_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertImpersonationDenied($admin, $admin);
    }

    public function test_nested_impersonation_is_forbidden(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $another = User::factory()->create();

        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target);
        $this->assertAuthenticatedAs($target);

        // Already impersonating: a second take is denied and the first stands.
        try {
            app(StartImpersonation::class)->handle($another);
            $this->fail('Nested impersonation should be forbidden.');
        } catch (AuthorizationException) {
            // expected
        }

        $this->assertAuthenticatedAs($target);
    }

    // --- Leaving impersonation: POST only, returns to where it began ---

    public function test_an_impersonator_can_leave_impersonation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        // No origin recorded → leave returns to the impersonated user's page.
        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target);
        $this->assertAuthenticatedAs($target);

        $this->post(route('users.impersonate.leave'))
            ->assertRedirect(route('users.show', $target));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonated_by'));
    }

    public function test_leaving_returns_to_where_impersonation_was_started(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        // Started from the user list → leave returns there, not the target's page.
        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target, route('users.index'));

        $this->post(route('users.impersonate.leave'))
            ->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_leaving_impersonation_is_not_reachable_by_navigation(): void
    {
        // Regression for GitHub #11: leaving is a state change, so a plain GET
        // navigation to the endpoint must not trigger it.
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target);

        $this->get('/users/impersonate/leave')->assertMethodNotAllowed();
        $this->assertAuthenticatedAs($target);
    }

    private function assertImpersonationDenied(User $actor, User $target): void
    {
        $this->actingAs($actor);

        try {
            app(StartImpersonation::class)->handle($target);
            $this->fail('Impersonation should have been forbidden.');
        } catch (AuthorizationException) {
            // expected — the write boundary denied it
        }

        $this->assertFalse(app(ImpersonateManager::class)->isImpersonating());
        $this->assertAuthenticatedAs($actor);
    }
}
