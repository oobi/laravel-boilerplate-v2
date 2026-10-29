<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\Demo\UserSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The demo seeders are the unit under test here, not fixture setup (tests.md).
 * They create accounts with a known password, so production is refused
 * (GitHub oobi#60).
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string}>
     */
    public static function demoSeeders(): array
    {
        return [
            'demo seeder' => [DemoSeeder::class],
            'demo user seeder, run on its own' => [UserSeeder::class],
        ];
    }

    /**
     * The `--force` a deploy script passes skips db:seed's own production prompt.
     *
     * @param  class-string  $seeder
     */
    #[DataProvider('demoSeeders')]
    public function test_it_refuses_to_run_in_production(string $seeder): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->assertThrows(
            fn () => Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]),
            RuntimeException::class,
            'will not run in production',
        );

        $this->assertSame(0, User::query()->count());
    }

    public function test_it_seeds_the_demo_accounts_outside_production(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertTrue(User::query()->where('email', 'admin@email.com')->where('is_super_admin', true)->exists());
    }
}
