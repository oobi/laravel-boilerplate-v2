<?php

namespace Tests\Feature\Admin\Users;

use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_impersonate_a_regular_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        // Impersonation lands on the target's own post-login destination — a
        // team-less user's is onboarding, not the admin dashboard.
        $this->actingAs($admin)
            ->get(route('users.impersonate', $target->id))
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
        $this->actingAs($admin)
            ->get(route('users.impersonate', $target->id))
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $this->assertAuthenticatedAs($target);
    }

    public function test_a_signed_next_overrides_the_default_landing(): void
    {
        // The "open team" flow wants to land in a specific team as its owner,
        // not the owner's generic home — carried as a signed `next`.
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $url = URL::signedRoute('users.impersonate', [
            'id' => $target->id,
            'next' => 'https://acme.example.test/somewhere',
        ]);

        $this->actingAs($admin)
            ->get($url)
            ->assertRedirect('https://acme.example.test/somewhere');

        $this->assertAuthenticatedAs($target);
    }

    public function test_an_unsigned_next_is_ignored(): void
    {
        // An open-redirect attempt with no signature falls back to the default
        // destination (a team-less target → onboarding).
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('users.impersonate', $target->id).'?next='.urlencode('https://evil.example/phish'))
            ->assertRedirect(route('team.onboarding'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_support_can_impersonate_a_regular_user(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        $this->actingAs($support)
            ->get(route('users.impersonate', $target->id));

        $this->assertAuthenticatedAs($target);
    }

    public function test_regular_users_cannot_impersonate(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->get(route('users.impersonate', $target->id))
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admins_cannot_be_impersonated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('users.impersonate', $otherAdmin->id));

        $this->assertAuthenticatedAs($admin);
    }

    #[DataProvider('inactiveUserBlockingSettings')]
    public function test_inactive_users_cannot_be_impersonated(bool $blockInactiveUsers): void
    {
        config()->set('auth.block_inactive_users', $blockInactiveUsers);
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->inactive()->create();

        $this->actingAs($admin)->from(route('users.index'))
            ->get(route('users.impersonate', $target))
            ->assertRedirect(route('users.index'))
            ->assertSessionMissing('impersonated_by');

        $this->assertAuthenticatedAs($admin);
    }

    /** @return array<string, array{bool}> */
    public static function inactiveUserBlockingSettings(): array
    {
        return [
            'suspension enforced' => [true],
            'suspension disabled' => [false],
        ];
    }

    public function test_a_user_cannot_impersonate_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('users.impersonate', $admin->id))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_nested_impersonation_is_forbidden(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $another = User::factory()->create();

        $this->actingAs($admin)->get(route('users.impersonate', $target->id));

        $this->get(route('users.impersonate', $another->id))->assertForbidden();

        $this->assertAuthenticatedAs($target);
    }

    public function test_an_impersonator_can_leave_impersonation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        // No referer to remember → leave returns to the impersonated user's page.
        $this->actingAs($admin)->get(route('users.impersonate', $target->id));
        $this->assertAuthenticatedAs($target);

        $this->get(route('users.impersonate.leave'))
            ->assertRedirect(route('users.show', $target));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonated_by'));
    }

    public function test_leaving_returns_to_where_impersonation_was_started(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        // Started from the user list → leave returns there, not the target's page.
        $this->actingAs($admin)->from(route('users.index'))
            ->get(route('users.impersonate', $target->id));

        $this->get(route('users.impersonate.leave'))
            ->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
    }
}
