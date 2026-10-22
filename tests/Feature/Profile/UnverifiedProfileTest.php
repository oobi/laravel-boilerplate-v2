<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Profile\EditProfile;
use App\Models\User;
use App\Notifications\Auth\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Someone who mistyped their address at sign-up can fix it themselves (GitHub
 * #46): the profile is open before verification, the password and two-factor
 * pages are not.
 */
class UnverifiedProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unverified_user_can_open_their_profile_but_not_the_other_tabs(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSeeHtml('href="'.route('profile.password').'"')
            ->assertDontSeeHtml('href="'.route('profile.two-factor').'"');

        $this->get(route('profile.password'))->assertRedirect(route('verification.notice'));
        $this->get(route('profile.two-factor'))->assertRedirect(route('verification.notice'));
    }

    public function test_an_unverified_user_can_correct_their_address_and_gets_a_new_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'typo@exmaple.com']);

        Livewire::actingAs($user)->test(EditProfile::class)
            ->set('email', 'right@example.com')
            ->set('current_password', 'password')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasNoErrors();

        $user = $user->fresh();
        $this->assertSame('right@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_the_verify_page_points_to_the_profile_to_fix_the_address(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('verification.notice'))
            ->assertSeeHtml('href="'.route('profile.edit').'"');
    }

    /** Photos wait for a verified address, so unverified sign-ups can't fill storage. */
    public function test_an_unverified_user_cant_upload_a_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->unverified()->create();

        Livewire::actingAs($user)->test(EditProfile::class)
            ->assertDontSeeHtml('x-ref="photo"')
            ->set('photo', UploadedFile::fake()->image('avatar.jpg'))
            ->assertForbidden();

        $this->assertNull($user->fresh()->profile_photo_path);
    }
}
