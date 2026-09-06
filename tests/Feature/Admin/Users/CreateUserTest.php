<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    }

    public function test_support_is_forbidden_from_creating_a_user(): void
    {
        $support = User::factory()->support()->create();

        $this->actingAs($support)->get('/admin/users/create')->assertForbidden();
    }

    public function test_admins_can_create_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'brand-new@example.com')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create');

        $this->assertDatabaseHas('users', [
            'first_name' => 'Brand',
            'last_name' => 'New User',
            'email' => 'brand-new@example.com',
            'active' => true,
        ]);
    }

    public function test_creating_a_user_with_a_taken_email_fails_validation_regardless_of_casing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'TAKEN@Example.com')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(2, User::count());
    }

    /** A soft-deleted user keeps their row, so their address stays reserved. */
    public function test_creating_a_user_with_a_soft_deleted_users_email_fails_validation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->create(['email' => 'gone@example.com'])->delete();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'gone@example.com')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_a_mixed_case_email_is_stored_lowercased(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New User')
            ->set('data.email', 'Brand.New@Example.COM')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create');

        $this->assertDatabaseHas('users', ['email' => 'brand.new@example.com']);
    }
}
