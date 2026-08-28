<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CreateUser;
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

        $this->actingAs($user)->get('/users/create')->assertForbidden();
    }

    public function test_admins_can_create_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->set('data.name', 'Brand New User')
            ->set('data.email', 'brand-new@example.com')
            ->set('data.password', 'password')
            ->set('data.password_confirmation', 'password')
            ->call('create');

        $this->assertDatabaseHas('users', [
            'name' => 'Brand New User',
            'email' => 'brand-new@example.com',
            'active' => true,
        ]);
    }
}
