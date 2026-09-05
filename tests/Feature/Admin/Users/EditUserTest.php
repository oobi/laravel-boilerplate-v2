<?php

namespace Tests\Feature\Admin\Users;

use App\Enums\SystemRole;
use App\Livewire\Admin\Users\EditUser;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class EditUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get("/admin/users/{$other->id}/edit")->assertForbidden();
    }

    public function test_admins_can_update_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.first_name', 'Updated')
            ->set('data.last_name', 'Name')
            ->set('data.system_role', SystemRole::SUPPORT->value)
            ->call('save')
            ->assertRedirect(route('users.show', $target));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertTrue($target->hasSystemRole(SystemRole::SUPPORT));
    }

    public function test_support_can_update_a_regular_user(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        Livewire::actingAs($support)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.first_name', 'Updated')
            ->set('data.last_name', 'Name')
            ->call('save')
            ->assertRedirect(route('users.show', $target));

        $this->assertSame('Updated Name', $target->fresh()->name);
    }

    public function test_support_is_forbidden_from_editing_a_super_admin(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->superAdmin()->create();

        $this->actingAs($support)->get("/admin/users/{$target->id}/edit")->assertForbidden();
    }

    public function test_super_admins_can_directly_reset_a_users_password(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->callAction('resetPassword', data: [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
    }

    public function test_support_sends_a_password_reset_link_instead_of_setting_one_directly(): void
    {
        Notification::fake();

        $support = User::factory()->support()->create();
        $target = User::factory()->create();
        $originalPassword = $target->password;

        Livewire::actingAs($support)
            ->test(EditUser::class, ['user' => $target])
            ->callAction('resetPassword');

        $this->assertSame($originalPassword, $target->fresh()->password);
        Notification::assertSentTo($target, ResetPassword::class);
    }
}
