<?php

namespace Tests\Feature\ThemeDemo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StyleDemoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/style-demo')->assertRedirect('/login');
    }

    public function test_users_without_admin_access_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/style-demo')->assertForbidden();
    }

    #[DataProvider('stylePageProvider')]
    public function test_admins_can_view_every_style_demo_page(string $uri): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get($uri)->assertStatus(200);
    }

    /** @return array<string, array{string}> */
    public static function stylePageProvider(): array
    {
        return [
            'overview' => ['/style-demo'],
            'empty table' => ['/style-demo/tables/empty'],
            'simple table' => ['/style-demo/tables/simple'],
            'maximalist table' => ['/style-demo/tables/maximalist'],
            'filament table empty' => ['/style-demo/tables/filament/empty'],
            'filament table simple' => ['/style-demo/tables/filament/simple'],
            'filament table maximalist' => ['/style-demo/tables/filament/maximalist'],
            'filament table custom header' => ['/style-demo/tables/filament/custom-header'],
            'daisy form' => ['/style-demo/forms/daisy'],
            'filament form' => ['/style-demo/forms/filament'],
            'components' => ['/style-demo/components'],
        ];
    }
}
