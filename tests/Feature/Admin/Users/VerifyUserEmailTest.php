<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Users;

use App\Enums\SystemPermission;
use App\Enums\UserAbility;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Support can unblock someone stuck on "verify your email" (GitHub #55):
 * "Mark email as verified" on the Show page, for users they cover, never
 * themselves.
 */
class VerifyUserEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_manager_can_mark_an_unverified_email_as_verified(): void
    {
        Event::fake([Verified::class]);
        $target = User::factory()->unverified()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowUser::class, ['user' => $target])
            ->assertActionVisible('verifyEmail')
            ->callAction('verifyEmail');

        $this->assertTrue($target->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($target));
    }

    public function test_it_is_only_offered_while_the_email_is_unverified(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowUser::class, ['user' => User::factory()->create()])
            ->assertActionHidden('verifyEmail');
    }

    public function test_it_needs_manage_users_and_coverage(): void
    {
        $target = User::factory()->unverified()->create();

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::VIEW_USERS)->create())
            ->test(ShowUser::class, ['user' => $target])
            ->assertActionHidden('verifyEmail');

        // Support can't act on someone holding more access than they do.
        $administrator = User::factory()->administrator()->unverified()->create();
        Livewire::actingAs(User::factory()->support()->create())
            ->test(ShowUser::class, ['user' => $administrator])
            ->assertActionHidden('verifyEmail');

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
        $this->assertFalse($administrator->fresh()->hasVerifiedEmail());
    }

    public function test_nobody_verifies_their_own_email_this_way(): void
    {
        $admin = User::factory()->superAdmin()->unverified()->create();

        $this->assertFalse($admin->can(UserAbility::VERIFY_EMAIL, $admin));
    }

    public function test_the_impersonation_note_points_to_it(): void
    {
        $target = User::factory()->unverified()->create(['first_name' => 'Dana', 'last_name' => 'Example']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowUser::class, ['user' => $target])
            ->mountAction('impersonate')
            ->assertMountedActionModalSee(__('admin.impersonate_unverified_can_verify'));
    }
}
