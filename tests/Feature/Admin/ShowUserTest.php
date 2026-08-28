<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ShowUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get("/users/{$other->id}")->assertForbidden();
    }

    public function test_admins_can_view_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['name' => 'Jane Doe']);

        $response = $this->actingAs($admin)->get("/users/{$target->id}");

        $response->assertStatus(200);
        $response->assertSee('Jane Doe');
    }

    public function test_a_soft_deleted_user_can_still_be_viewed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $target->delete();

        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->assertOk();
    }
}
