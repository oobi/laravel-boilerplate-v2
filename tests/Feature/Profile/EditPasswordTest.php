<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Profile\EditPassword;
use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Livewire\Livewire;
use Tests\TestCase;

class EditPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/profile/password')->assertRedirect('/login');
    }

    public function test_users_without_admin_access_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/profile/password')->assertForbidden();
    }

    public function test_admin_users_can_view_the_password_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/profile/password')->assertOk();
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

    public function test_updating_the_password_invalidates_other_devices_but_keeps_this_one(): void
    {
        Event::fake([OtherDeviceLogout::class]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditPassword::class)
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword', app(UpdatesUserPasswords::class))
            ->assertHasNoErrors();

        // logoutOtherDevices() fires this once the other sessions are dropped.
        Event::assertDispatched(OtherDeviceLogout::class);

        // The device that made the change stays signed in.
        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }

    public function test_authenticate_session_middleware_guards_the_web_group(): void
    {
        $webGroup = app(Kernel::class)->getMiddlewareGroups()['web'];

        // Without this middleware on the web group, a changed password hash
        // would never invalidate the user's sessions on their other devices.
        $this->assertContains(AuthenticateSession::class, $webGroup);
    }
}
