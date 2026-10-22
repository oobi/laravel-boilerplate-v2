<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Admin\Users\ShowUser;
use App\Livewire\Profile\EditPassword;
use App\Models\SocialAccount;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A Google link someone else made must not outlive the account's recovery
 * (#61): a reset link or an admin setting the password drops it, and an admin
 * can see and remove it from the Security panel.
 */
class GoogleLinkRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);
    }

    private function linkedUser(): User
    {
        return tap(User::factory()->create(), fn (User $user) => SocialAccount::factory()->for($user)->create(['email' => 'jo.personal@gmail.com']));
    }

    public function test_resetting_the_password_by_email_link_drops_the_google_link(): void
    {
        $user = $this->linkedUser();

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'a-new-long-password-1',
            'password_confirmation' => 'a-new-long-password-1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->googleAccount);
    }

    public function test_an_admin_setting_the_password_drops_the_google_link_and_says_so(): void
    {
        $user = $this->linkedUser();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowUser::class, ['user' => $user])
            ->callAction('resetPassword', data: [
                'password' => 'a-new-long-password-1',
                'password_confirmation' => 'a-new-long-password-1',
            ])
            ->assertNotified(Notification::make()
                ->title(__('admin.password_reset_success'))
                ->body(__('admin.password_reset_google_unlinked'))
                ->success());

        $this->assertNull($user->fresh()->googleAccount);
    }

    /** The owner changing it on their profile proved the current password: not a recovery. */
    public function test_changing_your_own_password_keeps_the_google_link(): void
    {
        $user = $this->linkedUser();

        Livewire::actingAs($user)
            ->test(EditPassword::class)
            ->set('current_password', 'password')
            ->set('password', 'a-new-long-password-1')
            ->set('password_confirmation', 'a-new-long-password-1')
            ->call('updatePassword', app(UpdatesUserPasswords::class));

        $this->assertNotNull($user->fresh()->googleAccount);
    }

    public function test_the_security_panel_shows_the_link_masked(): void
    {
        $user = $this->linkedUser();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowUser::class, ['user' => $user])
            ->assertSee('j•••@gmail.com')
            ->assertDontSee('jo.personal@gmail.com')
            ->assertSeeHtml("callPanelAction('security', 'unlink-google')");
    }

    public function test_an_admin_unlinks_it_only_after_confirming_their_password(): void
    {
        $user = $this->linkedUser();

        $component = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowUser::class, ['user' => $user])
            ->call('callPanelAction', 'security', 'unlink-google')
            ->assertSet('confirmingPassword', true);

        $this->assertNotNull($user->fresh()->googleAccount);

        $component->set('confirmablePassword', 'password')->call('confirmPassword');

        $this->assertNull($user->fresh()->googleAccount);
    }

    /** Coverage, as for every act on another user: support can't unlink an administrator. */
    public function test_an_admin_without_coverage_cant_unlink(): void
    {
        $administrator = User::factory()->administrator()->create();
        SocialAccount::factory()->for($administrator)->create();

        Livewire::actingAs(User::factory()->support()->create())
            ->test(ShowUser::class, ['user' => $administrator])
            ->assertDontSeeHtml("callPanelAction('security', 'unlink-google')")
            ->call('callPanelAction', 'security', 'unlink-google')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword')
            ->assertForbidden();

        $this->assertNotNull($administrator->fresh()->googleAccount);
    }
}
