<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Roles\ManageRoles;
use App\Livewire\Admin\Users\ListUsers;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The users list and the Roles screen cost the same however many users there
 * are, for a viewer who isn't a super admin (the super-admin bypass hid the
 * per-row queries, GitHub #24).
 */
class AdminQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    public function test_the_users_list_costs_no_query_per_user(): void
    {
        User::factory()->support()->create();
        $few = $this->queries(fn () => Livewire::actingAs($this->administrator->fresh())->test(ListUsers::class));

        User::factory()->count(8)->support()->create();
        User::factory()->count(4)->create();

        $this->assertSame($few, $this->queries(fn () => Livewire::actingAs($this->administrator->fresh())->test(ListUsers::class)));
    }

    public function test_the_roles_screen_costs_no_query_per_holder(): void
    {
        User::factory()->support()->create();
        $support = Role::findByName('Support');
        $few = $this->queries(fn () => Livewire::actingAs($this->administrator->fresh())->test(ManageRoles::class, ['role' => $support->fresh()]));

        User::factory()->count(12)->support()->create();

        $this->assertSame($few, $this->queries(fn () => Livewire::actingAs($this->administrator->fresh())->test(ManageRoles::class, ['role' => $support->fresh()])));
    }

    private function queries(callable $render): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $render(); // warm-up: the first render also pays one-off lookups

        DB::flushQueryLog();
        DB::enableQueryLog();
        $render();

        return count(DB::getQueryLog());
    }
}
