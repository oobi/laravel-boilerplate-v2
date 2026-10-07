<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\EditUser;
use App\Models\User;
use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\FormSection;
use App\Support\Panels\Registry\PanelRegistry;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
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

    public function test_the_page_header_slot_renders_intentional_markup(): void
    {
        // The page-header title is passed as a slot here (a "You" badge next to
        // the heading). Slots carry HtmlString, so escaping the string-prop case
        // must not double-escape this intentional markup.
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get("/admin/users/{$admin->id}/edit")
            ->assertOk()
            ->assertSee(__('admin.you'))
            ->assertSee('<span class="flex items-center gap-2">', false)
            ->assertDontSee('&lt;span', false);
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

    /** Your own email changes on your profile, which asks for the current password (GitHub #17). */
    public function test_an_admin_cant_change_their_own_email_here(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'me@example.com']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $admin])
            ->assertFormFieldDisabled('email')
            ->set('data.email', 'elsewhere@example.com')
            ->set('data.first_name', 'Renamed')
            ->call('save');

        $admin->refresh();
        $this->assertSame('me@example.com', $admin->email);
        $this->assertSame('Renamed', $admin->first_name);
    }

    /** The write boundary refuses it too, should a form section expose the field. */
    public function test_saving_refuses_a_change_to_your_own_email_from_any_section(): void
    {
        PanelRegistry::for('users.edit')->classes = [OpenEmailFormSection::class];
        $admin = User::factory()->superAdmin()->create(['email' => 'me@example.com']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $admin])
            ->set('data.email', 'elsewhere@example.com')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('me@example.com', $admin->fresh()->email);
    }

    public function test_the_email_note_links_to_your_profile(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['user' => $admin])
            ->assertSeeHtml('href="'.route('profile.edit').'"');
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

/** An add-on style section that exposes the email field without locking it. */
class OpenEmailFormSection implements FormSection
{
    use HasPanelMetadata;

    /** @return array<int, Component> */
    public function components(Model $subject): array
    {
        return [TextInput::make('email')->email()];
    }
}
