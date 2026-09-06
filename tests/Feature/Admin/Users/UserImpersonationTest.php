<?php

namespace Tests\Feature\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_impersonate_a_regular_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('users.impersonate', $target->id))
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($target);
        $this->assertEquals($admin->id, session('impersonated_by'));
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

        $this->actingAs($admin)->get(route('users.impersonate', $target->id));
        $this->assertAuthenticatedAs($target);

        $this->get(route('users.impersonate.leave'))
            ->assertRedirect('/admin/users');

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonated_by'));
    }
}
