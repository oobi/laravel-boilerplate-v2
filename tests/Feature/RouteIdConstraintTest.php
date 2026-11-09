<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A model id in a URL that isn't digits fitting a bigint is a 404 before any
 * query runs. Postgres errors on such a value rather than finding nothing, so
 * it was a 500 there (GitHub oobi#89). SQLite and MySQL return 404 either way:
 * run this class on Postgres to see the difference.
 */
class RouteIdConstraintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function junkIds(): array
    {
        $routes = [
            'user page' => ['users.show', 'user'],
            'user edit page' => ['users.edit', 'user'],
            'role edit page' => ['roles.edit', 'role'],
            'audit entry' => ['audit.show', 'entry'],
            'export download' => ['filament.exports.download', 'export'],
            'import failed rows download' => ['filament.imports.failed-rows.download', 'import'],
        ];

        $cases = [];

        foreach ($routes as $label => [$routeName, $parameter]) {
            foreach (['letters' => 'abc', 'decimal' => '1.5', 'past bigint' => '99999999999999999999'] as $idLabel => $id) {
                $cases["{$label}, {$idLabel}"] = [$routeName, $parameter, $id];
            }
        }

        return $cases;
    }

    #[DataProvider('junkIds')]
    public function test_a_junk_id_is_not_found(string $routeName, string $parameter, string $id): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route($routeName, [$parameter => $id]))
            ->assertNotFound();
    }

    public function test_a_real_id_still_resolves(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('users.show', $admin))
            ->assertOk();
    }
}
