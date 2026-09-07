<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Profile\EditPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Livewire\Livewire;
use Tests\TestCase;

class EditPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/profile/password')->assertRedirect('/login');
    }

    public function test_users_can_view_the_password_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile/password')->assertOk();
    }

    public function test_users_can_update_their_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditPassword::class)
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword', app(UpdatesUserPasswords::class));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_updating_the_password_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditPassword::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword', app(UpdatesUserPasswords::class))
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
