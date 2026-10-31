<?php

namespace Tests\Feature\Admin\Roles;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\CreateRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_is_forbidden_from_creating_a_role(): void
    {
        $support = User::factory()->support()->create();

        $this->actingAs($support)->get('/admin/roles/create')->assertForbidden();
    }

    public function test_an_unknown_scope_is_not_found(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/roles/create?scope=nope')->assertNotFound();
        $this->actingAs($admin)->get('/admin/roles/create?scope[]=nope')->assertNotFound(); // an array, not a 500 (#40)
    }

    public function test_super_admins_can_create_a_role_with_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $component = Livewire::actingAs($admin)
            ->test(CreateRole::class)
            ->set('data.name', 'Editor')
            ->set('data.permissions_user_management', [SystemPermission::MANAGE_USERS->value])
            ->call('create');

        $this->assertDatabaseHas('roles', ['name' => 'Editor']);

        $role = Role::findByName('Editor');

        $component->assertRedirect(route('roles.edit', $role));

        $this->assertTrue($role->fresh()->checkPermissionTo(SystemPermission::MANAGE_USERS->value));
    }

    public function test_super_admins_can_create_a_role_with_a_custom_badge_color(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateRole::class)
            ->set('data.name', 'Editor')
            ->set('data.color', DaisyColor::WARNING->value)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(DaisyColor::WARNING, Role::findByName('Editor')->color);
    }

    public function test_super_admins_can_create_a_role_that_requires_two_factor(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(CreateRole::class)
            ->set('data.name', 'Editor')
            ->set('data.requires_two_factor', true)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertTrue(Role::findByName('Editor')->requires_two_factor);
    }

    /** Screen readers read the toggle's helper text with it (GitHub oobi#51; Filament 5.10.3+). */
    public function test_the_two_factor_toggle_is_described_by_its_helper_text(): void
    {
        $html = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateRole::class)
            ->assertSeeHtml('id="form.requires_two_factor-helper-text"')
            ->html();

        $this->assertMatchesRegularExpression('/<button\b(?=[^>]*\brole="switch")(?=[^>]*\bid="form\.requires_two_factor")(?=[^>]*\baria-describedby="form\.requires_two_factor-helper-text")[^>]*>/', $html);
    }

    /** Our own badge colour picker is tied to its error and marked invalid, like Filament's fields (GitHub oobi#51). */
    public function test_the_badge_colour_picker_is_described_by_its_error(): void
    {
        $html = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(CreateRole::class)
            ->set('data.name', 'Billing')
            ->set('data.color', null)
            ->call('create')
            ->assertHasErrors('data.color')
            ->assertSeeHtml('id="form.color-error"')
            ->html();

        $this->assertMatchesRegularExpression('/<button\b(?=[^>]*\bid="form\.color")(?=[^>]*\baria-describedby="form\.color-error")(?=[^>]*\baria-invalid="true")[^>]*>/', $html);
    }

    public function test_the_two_factor_help_text_pluralises_the_grace_period(): void
    {
        $admin = User::factory()->superAdmin()->create();

        config()->set('auth.two_factor.grace_days', 1);
        Livewire::actingAs($admin)->test(CreateRole::class)->assertSee('within 1 day of');

        config()->set('auth.two_factor.grace_days', 14);
        Livewire::actingAs($admin)->test(CreateRole::class)->assertSee('within 14 days of');
    }
}
