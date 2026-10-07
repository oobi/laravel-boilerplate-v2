<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\EditProfile;
use App\Models\User;
use App\Support\Auth\PasswordChecks;
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
        $this->get('/profile')->assertRedirect('/login');
    }

    /** The profile is not an admin page — any signed-in, verified user reaches it. */
    public function test_users_without_admin_access_can_view_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_admin_users_can_view_their_profile(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/profile')->assertStatus(200);
    }

    public function test_users_can_update_their_profile_information(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('first_name', 'Updated')
            ->set('last_name', 'Name')
            ->set('email', 'updated@example.com')
            ->set('current_password', 'password')
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
            ->set('current_password', 'password')
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

    /** A new email is a route to a password reset, so it takes the password (GitHub #16). */
    public function test_changing_the_email_needs_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('email', 'new@example.com')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasErrors(['current_password' => 'required']);

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_changing_the_email_with_a_wrong_password_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('email', 'new@example.com')
            ->set('current_password', 'not-my-password')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasErrors(['current_password' => __('auth.current_password_mismatch')]);

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_the_password_field_appears_only_once_the_email_changes(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->assertDontSeeHtml('id="current_password"')
            ->set('email', 'new@example.com')
            ->assertSeeHtml('id="current_password"');
    }

    /** The address is stored lowercased, so a case-only edit is no change. */
    public function test_a_case_only_email_edit_needs_no_password(): void
    {
        $user = User::factory()->create(['email' => 'same@example.com']);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('email', ' Same@Example.com ')
            ->assertDontSeeHtml('id="current_password"')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasNoErrors();

        $this->assertSame('same@example.com', $user->fresh()->email);
    }

    /** The password an email change asks for is counted like any other (GitHub #18). */
    public function test_too_many_wrong_passwords_on_an_email_change_are_throttled(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $page = Livewire::actingAs($user)->test(EditProfile::class)->set('email', 'new@example.com');

        foreach (range(1, 5) as $attempt) {
            $page->set('current_password', 'wrong-'.$attempt)->call('updateProfileInformation', app(UpdatesUserProfileInformation::class));
        }

        $page->set('current_password', 'password')->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasErrors(['current_password'])
            ->assertSee('Too many wrong passwords.');

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_a_name_change_still_saves_while_throttled(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $attempt) {
            PasswordChecks::failed($user);
        }

        Livewire::actingAs($user)->test(EditProfile::class)
            ->set('first_name', 'Renamed')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasNoErrors();

        $this->assertSame('Renamed', $user->fresh()->first_name);
    }

    /** Without an email change the password isn't checked at all, so it can't be used to guess. */
    public function test_a_password_sent_without_an_email_change_is_ignored(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $attempt) {
            PasswordChecks::failed($user);
        }

        Livewire::actingAs($user)->test(EditProfile::class)
            ->set('first_name', 'Renamed')
            ->set('current_password', 'not-my-password')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasNoErrors();

        $this->assertSame('Renamed', $user->fresh()->first_name);
    }
}
