<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\ListUsers;
use App\Models\User;
use App\Support\UserDeletionGuards;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An add-on can refuse a user's deletion with a reason (UserDeletionGuards,
 * GitHub oobi#28). Every deletion path honours it, super admins included, and
 * the Users screen says why. A stand-in guard here, so this runs without the
 * teams tier.
 */
class UserDeletionGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $kept;

    private bool $onlyPermanently = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kept = User::factory()->create();
        UserDeletionGuards::register(fn (User $user, bool $permanently): ?string => $user->is($this->kept) && ($permanently || ! $this->onlyPermanently)
            ? 'Kept for a reason.'
            : null);
    }

    public function test_a_guarded_user_cannot_be_deleted_even_by_a_super_admin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertThrows(fn () => $this->kept->delete(), AuthorizationException::class, 'Kept for a reason.');
        $this->assertNotSoftDeleted($this->kept);
    }

    public function test_the_delete_dialog_gives_the_reason_and_no_way_to_confirm(): void
    {
        $action = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListUsers::class)
            ->mountAction(TestAction::make('delete')->table($this->kept))
            ->instance()
            ->getMountedAction();

        $this->assertSame(__('admin.delete_blocked_heading', ['name' => $this->kept->name]), $action->getModalHeading());
        $this->assertSame('Kept for a reason.', $action->getModalDescription());
        $this->assertNull($action->getModalSubmitAction());
    }

    public function test_the_row_delete_refuses_a_guarded_user(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListUsers::class)
            ->callAction(TestAction::make('delete')->table($this->kept))
            ->assertForbidden();

        $this->assertNotSoftDeleted($this->kept);
    }

    public function test_bulk_delete_keeps_a_guarded_user_and_deletes_the_rest(): void
    {
        $other = User::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListUsers::class)
            ->selectTableRecords([$this->kept, $other])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertStringContainsString('Kept for a reason.', $this->sentNotificationBodies());
        $this->assertNotSoftDeleted($this->kept);
        $this->assertSoftDeleted($other);
    }

    public function test_empty_trash_keeps_a_guarded_user_and_says_why(): void
    {
        $this->onlyPermanently = true;
        $other = User::factory()->create();
        $this->kept->delete();
        $other->delete();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListUsers::class)
            ->callAction('emptyTrash')
            ->assertNotified(Notification::make()
                ->title(__('admin.empty_trash_success', ['count' => 1]))
                ->body('Kept for a reason.')
                ->success());

        $this->assertSoftDeleted($this->kept);
        $this->assertModelMissing($other);
    }

    /** Every notification body sent so far, read the way Filament's assertNotified() reads them. */
    private function sentNotificationBodies(): string
    {
        $notifications = new Notifications;
        $notifications->mount();

        return $notifications->notifications->map(fn (Notification $notification): string => (string) $notification->getBody())->implode(' ');
    }

    public function test_an_unguarded_user_is_deleted_as_before(): void
    {
        $other = User::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListUsers::class)
            ->callAction(TestAction::make('delete')->table($other));

        $this->assertSoftDeleted($other);
    }
}
