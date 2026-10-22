<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Admin\Users\EditUser;
use App\Livewire\Profile\EditProfile;
use App\Models\User;
use App\Notifications\Auth\EmailAddressChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every email change tells the previous address, so a takeover through a
 * stolen session (or a mistaken admin edit) gets noticed (GitHub #49).
 */
class EmailChangeNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function assertOldAddressTold(string $old, string $new): void
    {
        Notification::assertSentOnDemand(EmailAddressChanged::class, function (EmailAddressChanged $notification, array $channels, AnonymousNotifiable $notifiable) use ($old, $new): bool {
            return $notifiable->routes['mail'] === $old
                && str_contains(implode(' ', $notification->toMail($notifiable)->introLines), EmailAddressChanged::mask($new))
                && ! str_contains(implode(' ', $notification->toMail($notifiable)->introLines), $new);
        });
        Notification::assertSentOnDemandTimes(EmailAddressChanged::class, 1);
    }

    public function test_a_self_service_change_tells_the_old_address(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        Livewire::actingAs($user)->test(EditProfile::class)
            ->set('email', 'new@example.com')
            ->set('current_password', 'password')
            ->call('updateProfileInformation', app(UpdatesUserProfileInformation::class))
            ->assertHasNoErrors();

        $this->assertOldAddressTold('old@example.com', 'new@example.com');
    }

    public function test_an_admin_change_tells_the_old_address(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(EditUser::class, ['user' => $user])
            ->set('data.email', 'new@example.com')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertOldAddressTold('old@example.com', 'new@example.com');
    }

    public function test_a_save_that_keeps_the_address_sends_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'same@example.com']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(EditUser::class, ['user' => $user])
            ->set('data.first_name', 'Renamed')
            ->set('data.email', ' Same@Example.com ')
            ->call('save');

        Notification::assertSentOnDemandTimes(EmailAddressChanged::class, 0);
    }

    public function test_the_new_address_is_masked(): void
    {
        $this->assertSame('j•••@example.com', EmailAddressChanged::mask('jane.doe@example.com'));
    }
}
