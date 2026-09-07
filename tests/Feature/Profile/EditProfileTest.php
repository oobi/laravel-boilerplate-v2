<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_users_without_admin_access_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/profile')->assertForbidden();
    }

    public function test_admin_users_can_view_their_profile(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/profile')->assertStatus(200);
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

    /**
     * EditProfile calls the Fortify action directly, bypassing the controller
     * that would normally canonicalize the address — issue #8. Uniqueness must
     * therefore be checked against the normalized value here too.
     */
    public function test_users_cannot_take_another_users_email_regardless_of_casing(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('email', 'TAKEN@Example.com')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasErrors('email');

        $this->assertNotSame('taken@example.com', $user->fresh()->email);
    }

    public function test_a_mixed_case_email_is_stored_lowercased(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('email', 'Mixed.Case@Example.TEST')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasNoErrors();

        $this->assertSame('mixed.case@example.test', $user->fresh()->email);
    }

    public function test_users_keeping_their_own_email_are_not_reported_as_a_duplicate(): void
    {
        $user = User::factory()->create(['email' => 'keeper@example.com']);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('first_name', 'Still')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasNoErrors();

        $this->assertSame('keeper@example.com', $user->fresh()->email);
    }
}
