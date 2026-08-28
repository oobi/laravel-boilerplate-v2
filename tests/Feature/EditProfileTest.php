<?php

namespace Tests\Feature;

use App\Livewire\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Livewire;
use Tests\TestCase;

class EditProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_profile_screen(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_users_can_view_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertStatus(200);
    }

    public function test_users_can_update_their_profile_information(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('name', 'Updated Name')
            ->set('email', 'updated@example.com')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class));

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('updated@example.com', $user->fresh()->email);
    }

    public function test_users_can_update_their_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword', app(UpdatesUserPasswords::class));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
