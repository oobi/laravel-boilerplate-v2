<?php

namespace Tests\Feature\Admin\Users;

use App\Actions\Impersonation\StartImpersonation;
use App\Livewire\Admin\Users\ListUsers;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\User;
use App\Support\Auth\Destination;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
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

        // Impersonation lands on the target's own post-login destination, not the
        // admin dashboard (Tests\Feature\Teams\TeamLandingTest for a team member).
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('impersonate')
            ->assertRedirect(Destination::home($target));

        $this->assertAuthenticatedAs($target);
        $this->assertEquals($admin->id, session('impersonated_by'));
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

    public function test_impersonating_asks_for_confirmation_first(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['first_name' => 'Dana', 'last_name' => 'Example']);
        $requiresConfirmation = fn (Action $action): bool => $action->isConfirmationRequired();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertActionExists('impersonate', $requiresConfirmation)
            ->mountAction('impersonate')
            ->assertMountedActionModalSee(__('admin.impersonate_confirm', ['name' => 'Dana Example']));

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertActionExists(TestAction::make('impersonate')->table($target), $requiresConfirmation);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_the_show_page_explains_why_an_unverified_user_cannot_be_impersonated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->unverified()->create(['first_name' => 'Dana', 'last_name' => 'Example']);

        // The action stays visible so the admin learns why, but its modal has
        // no submit, and forcing the call is refused at the write boundary.
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertActionVisible('impersonate')
            ->assertActionExists('impersonate', fn (Action $action): bool => $action->getModalSubmitAction() === null)
            ->mountAction('impersonate')
            ->assertMountedActionModalSee([
                __('admin.impersonate_unverified_heading', ['name' => 'Dana Example']),
                __('admin.impersonate_unverified'),
            ])
            ->callMountedAction()
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_the_unverified_hint_is_only_shown_to_someone_who_could_otherwise_impersonate(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->unverified()->superAdmin()->create();

        Livewire::actingAs($support)
            ->test(ShowUser::class, ['user' => $target])
            ->assertActionHidden('impersonate');
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

    public function test_unverified_users_cannot_be_impersonated(): void
    {
        $this->assertImpersonationDenied(
            User::factory()->superAdmin()->create(),
            User::factory()->unverified()->create(),
        );
    }

    public function test_trashed_users_cannot_be_impersonated(): void
    {
        $target = User::factory()->create();
        $target->delete();

        $this->assertImpersonationDenied(User::factory()->superAdmin()->create(), $target);
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

    public function test_logging_out_while_impersonating_only_stops_impersonating(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['remember_token' => 'targets-own-token']);

        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target);

        // A real logout would end the admin's session and rotate the target's
        // remember token, signing them out on their own devices.
        $this->post(route('logout'))->assertRedirect(route('users.show', $target));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(app(ImpersonateManager::class)->isImpersonating());
        $this->assertSame('targets-own-token', $target->fresh()->remember_token);
    }

    public function test_logout_goes_ahead_when_impersonation_cannot_be_left(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target);

        // lab404 swallows its own failures: without the fallback a stuck
        // session would bounce every logout back to a redirect.
        $this->partialMock(ImpersonateManager::class, fn ($manager) => $manager->shouldReceive('leave')->andReturnFalse());

        $this->post(route('logout'));

        $this->assertGuest();
    }

    public function test_logging_out_without_impersonating_still_logs_out(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }

    public function test_the_confirm_password_page_offers_a_way_out_of_impersonation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target);

        $this->get(route('password.confirm'))
            ->assertOk()
            ->assertSeeHtml('action="'.route('users.impersonate.leave').'"');
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

    public function test_error_pages_still_offer_a_way_out_of_impersonation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target);

        // The impersonated non-admin is refused the admin area: the error page
        // keeps the banner's leave form (a 404 is in TeamLandingTest, which has a
        // non-admin route to miss on).
        $leaveForm = 'action="'.route('users.impersonate.leave').'"';

        $this->get(route('dashboard'))->assertForbidden()->assertSeeHtml($leaveForm);
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
