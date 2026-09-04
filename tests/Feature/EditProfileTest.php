<?php

namespace Tests\Feature;

use App\Livewire\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Livewire;
use Tests\TestCase;

class EditProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_profile_screen(): void
    {
        $this->get('/admin/profile')->assertRedirect('/login');
    }

    public function test_users_can_view_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/profile')->assertStatus(200);
    }

    public function test_users_can_update_their_profile_information(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('first_name', 'Updated')
            ->set('last_name', 'Name')
            ->set('email', 'updated@example.com')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class));

        $user = $user->fresh();
        $this->assertSame('Updated', $user->first_name);
        $this->assertSame('Name', $user->last_name);
        $this->assertSame('updated@example.com', $user->email);
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

    public function test_users_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('first_name', $user->first_name)
            ->set('last_name', $user->last_name)
            ->set('email', $user->email)
            ->set('photo', UploadedFile::fake()->image('avatar.jpg'))
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class));

        $user = $user->fresh();
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }

    public function test_users_can_remove_their_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->updateProfilePhoto(UploadedFile::fake()->image('avatar.jpg'));
        $path = $user->profile_photo_path;

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->call('removeProfilePhoto');

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($path);
    }
}
