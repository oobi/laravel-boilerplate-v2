<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemRole;
use App\Livewire\Admin\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_users_list(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_users_without_admin_access_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_admins_can_view_the_users_list(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/users');

        $response->assertStatus(200);
    }

    public function test_the_table_can_search_users_by_name(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $match = User::factory()->create(['first_name' => 'Findable', 'last_name' => 'Person']);
        $other = User::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->set('tableSearch', 'Findable')
            ->assertSee($match->name)
            ->assertDontSee($other->name);
    }

    public function test_a_non_self_user_can_be_deactivated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('toggleActive', $target);

        $this->assertFalse($target->fresh()->active);
    }

    public function test_an_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create(['active' => true]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('toggleActive', $admin);
    }

    public function test_admins_can_empty_the_trash_except_their_own_account(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $admin->delete();
        $trashed = User::factory()->count(2)->create();
        $trashed->each->delete();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callAction('emptyTrash');

        $this->assertModelMissing($trashed->first());
        $this->assertModelMissing($trashed->last());
        $this->assertNotNull($admin->fresh());
    }

    public function test_a_non_privileged_admin_cannot_empty_the_trash(): void
    {
        $support = User::factory()->create(['system_role' => SystemRole::SUPPORT]);
        User::factory()->create()->delete();

        Livewire::actingAs($support)
            ->test(ListUsers::class)
            ->assertActionHidden('emptyTrash');
    }
}
