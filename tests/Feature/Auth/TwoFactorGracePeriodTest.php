<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TwoFactorGracePeriodTest extends TestCase
{
    use RefreshDatabase;

    private function flaggedRole(): Role
    {
        return Role::create(['name' => 'Admin', 'requires_two_factor' => true]);
    }

    private function subjectUser(?int $graceStartedDaysAgo = null): User
    {
        $factory = User::factory();

        if ($graceStartedDaysAgo !== null) {
            $factory = $factory->graceStartedDaysAgo($graceStartedDaysAgo);
        }

        return tap($factory->create())->assignRole($this->flaggedRole());
    }

    private function login(User $user, string $password = 'password'): TestResponse
    {
        return $this->post('/login', ['email' => $user->email, 'password' => $password]);
    }

    public function test_nothing_is_enforced_while_no_role_requires_two_factor(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'Editor']));

        $this->login($user);

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->two_factor_grace_started_at);
        $this->get('/profile')->assertOk()->assertDontSee(__('auth.two_factor_setup_cta'));
    }

    public function test_only_members_of_a_flagged_role_are_nagged(): void
    {
        $this->flaggedRole();
        $unflagged = User::factory()->create();
        $unflagged->assignRole(Role::create(['name' => 'Editor']));

        $this->actingAs($unflagged)->get('/profile')->assertOk()->assertDontSee(__('auth.two_factor_setup_cta'));
    }

    public function test_first_login_starts_the_grace_clock_and_a_later_login_does_not_move_it(): void
    {
        $user = $this->subjectUser();

        $this->login($user);
        $startedAt = $user->fresh()->two_factor_grace_started_at;
        $this->assertNotNull($startedAt);

        Auth::logout();
        $this->travel(3)->days();
        $this->login($user);

        $this->assertTrue($startedAt->equalTo($user->fresh()->two_factor_grace_started_at));
    }

    public function test_banner_counts_down_and_warns_of_lockout(): void
    {
        $user = $this->subjectUser(graceStartedDaysAgo: 4);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee(trans_choice('auth.two_factor_grace_warning', 10))
            ->assertSee('ui-banner-warning', escape: false)
            ->assertSee(route('profile.two-factor'));
    }

    public function test_banner_escalates_in_the_last_two_days(): void
    {
        $user = $this->subjectUser(graceStartedDaysAgo: 12);

        $this->actingAs($user)->get('/profile')
            ->assertSee(trans_choice('auth.two_factor_grace_warning', 2))
            ->assertSee('ui-banner-error', escape: false);
    }

    public function test_banner_says_the_grace_period_has_ended_in_a_session_that_outlived_it(): void
    {
        $user = $this->subjectUser(graceStartedDaysAgo: 20);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee(__('auth.two_factor_grace_ended'));
    }

    public function test_login_succeeds_up_to_the_deadline_and_is_refused_from_it(): void
    {
        $user = $this->subjectUser();
        $this->login($user);
        Auth::logout();

        $this->travel(14)->days();
        $this->travel(-1)->seconds();
        $this->login($user);
        $this->assertAuthenticatedAs($user);
        Auth::logout();

        $this->travel(1)->seconds();
        $this->login($user)->assertSessionHasErrors(['email' => __('auth.two_factor_locked_out')]);
        $this->assertGuest();
    }

    public function test_a_wrong_password_gets_the_generic_error_not_the_lockout_message(): void
    {
        $user = $this->subjectUser(graceStartedDaysAgo: 20);

        $this->login($user, 'wrong-password')->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_an_inactive_locked_out_user_gets_the_generic_error(): void
    {
        $user = $this->subjectUser(graceStartedDaysAgo: 20);
        $user->update(['active' => false]);

        $this->login($user)->assertSessionHasErrors(['email' => __('auth.inactive')]);
    }

    public function test_remember_me_cookie_is_refused_once_locked_out(): void
    {
        $user = $this->subjectUser();
        $cookieName = Auth::guard('web')->getRecallerName();
        $cookie = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])
            ->getCookie($cookieName)
            ->getValue();
        $rememberToken = $user->fresh()->getRememberToken();
        session()->flush();
        $this->app['auth']->forgetGuards();

        $this->travel(15)->days();

        $this->withCookie($cookieName, $cookie)->get('/profile')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.two_factor_locked_out')]);
        $this->assertGuest();
        $this->assertNotSame($rememberToken, $user->fresh()->getRememberToken());
    }

    public function test_remember_me_cookie_still_works_within_grace(): void
    {
        $user = $this->subjectUser();
        $cookieName = Auth::guard('web')->getRecallerName();
        $cookie = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])
            ->getCookie($cookieName)
            ->getValue();
        session()->flush();
        $this->app['auth']->forgetGuards();

        $this->withCookie($cookieName, $cookie)->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_confirmed_two_factor_lifts_the_mandate(): void
    {
        $user = User::factory()->twoFactorEnabled()->graceStartedDaysAgo(20)->create();
        $user->assignRole($this->flaggedRole());

        $this->login($user)->assertSessionHasNoErrors();
        $this->get('/profile')->assertDontSee(__('auth.two_factor_setup_cta'));
    }

    public function test_an_unverified_user_is_not_held_to_the_mandate(): void
    {
        $user = User::factory()->unverified()->graceStartedDaysAgo(20)->create();
        $user->assignRole($this->flaggedRole());

        $this->login($user)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admins_are_nagged_but_never_locked_out(): void
    {
        $this->flaggedRole();
        $admin = User::factory()->superAdmin()->graceStartedDaysAgo(60)->create();

        $this->login($admin)->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($admin);
        $this->get('/profile')
            ->assertSee(__('auth.two_factor_required_super_admin'))
            ->assertDontSee(__('auth.two_factor_grace_ended'));
    }

    public function test_super_admins_are_not_nagged_while_no_role_requires_two_factor(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/profile')->assertDontSee(__('auth.two_factor_required_super_admin'));
    }

    public function test_unflagging_the_role_lets_a_locked_out_user_back_in(): void
    {
        $user = $this->subjectUser(graceStartedDaysAgo: 20);
        $this->login($user)->assertSessionHasErrors('email');

        Role::findByName('Admin')->update(['requires_two_factor' => false]);

        $this->login($user)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_flagging_a_role_applies_at_once_to_its_existing_members(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $user = tap(User::factory()->create())->assignRole($role);
        $this->actingAs($user)->get('/profile')->assertDontSee(__('auth.two_factor_setup_cta'));

        $role->update(['requires_two_factor' => true]);

        $this->get('/profile')->assertSee(__('auth.two_factor_setup_cta'));
    }

    public function test_the_kill_switch_disables_all_enforcement(): void
    {
        config()->set('auth.two_factor.enforcement_enabled', false);
        $user = $this->subjectUser(graceStartedDaysAgo: 20);

        $this->login($user)->assertSessionHasNoErrors();
        $this->get('/profile')->assertDontSee(__('auth.two_factor_setup_cta'));
    }
}
