<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get("/admin/users/{$other->id}/edit")->assertForbidden();
    }

    public function test_admins_can_update_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.first_name', 'Updated')
            ->set('data.last_name', 'Name')
            ->call('save')
            ->assertRedirect(route('users.show', $target));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
    }

    public function test_support_can_update_a_regular_user(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        Livewire::actingAs($support)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.first_name', 'Updated')
            ->set('data.last_name', 'Name')
            ->call('save')
            ->assertRedirect(route('users.show', $target));

        $this->assertSame('Updated Name', $target->fresh()->name);
    }

    public function test_support_is_forbidden_from_editing_a_super_admin(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->superAdmin()->create();

        $this->actingAs($support)->get("/admin/users/{$target->id}/edit")->assertForbidden();
    }

    /**
     * Regression for issue #7: the edit form validated email format but not
     * uniqueness, so saving a taken address hit the DB unique index and raised
     * a QueryException instead of a field error.
     */
    public function test_updating_a_user_to_a_taken_email_fails_validation_regardless_of_casing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.email', 'TAKEN@Example.com')
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertNotSame('taken@example.com', $target->fresh()->email);
    }

    /** A soft-deleted user keeps their row, so their address stays reserved. */
    public function test_updating_a_user_to_a_soft_deleted_users_email_fails_validation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        User::factory()->create(['email' => 'gone@example.com'])->delete();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.email', 'gone@example.com')
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_a_user_keeping_their_own_email_is_not_reported_as_a_duplicate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['email' => 'keeper@example.com']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.first_name', 'Still')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('keeper@example.com', $target->fresh()->email);
    }

    /**
     * Regression for issue #8: a mixed-case email saved here used to be stored
     * verbatim, while Fortify lowercases the login identifier — locking the
     * account out on a case-sensitive connection.
     */
    public function test_a_mixed_case_email_is_stored_lowercased(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $target])
            ->set('data.email', 'Mixed.Case@Example.TEST')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('mixed.case@example.test', $target->fresh()->email);
    }
}
