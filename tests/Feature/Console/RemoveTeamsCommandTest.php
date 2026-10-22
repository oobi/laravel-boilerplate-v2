<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * bp:remove-teams (GitHub #22) run against a small scratch tree, never this
 * repo: it strips, checks, and only then removes, putting everything back if
 * a check or composer fails. Composer and artisan are faked; `php -l` runs for
 * real. The real fenced files are covered by Tests\Feature\Teams\TierFencesTest.
 */
class RemoveTeamsCommandTest extends TestCase
{
    private string $root;

    private const ROUTES = <<<'PHP'
        <?php

        $adminPath = 'admin';

        // teams:start
        $adminPath = config('teams.admin_path');
        // teams:end

        Route::prefix($adminPath)->group(fn () => null);
        PHP;

    private const USER = <<<'PHP'
        <?php

        class User
        {
            // teams:start
            // teams:canonical: use HasRoles;
            use HasRoles, HasTeams;
            // teams:end
        }
        PHP;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/remove-teams-'.uniqid();

        $this->writeFile('routes/web.php', self::ROUTES);
        $this->writeFile('app/Models/User.php', self::USER);
        $this->writeFile('app/Plain.php', "<?php\n// mentions teams:start mid-line, so not a fence\n");
        $this->writeFile('tests/Feature/Teams/TeamTest.php', '<?php');
        $this->writeFile('tests/Unit/Support/Setup/MarkerStripperTest.php', "<?php\n// use Concise\\Teams\\Bar;\n");
        $this->writeFile('packages/teams/composer.json', '{}');
        $this->writeFile('composer.json', json_encode([
            'repositories' => [['type' => 'path', 'url' => 'packages/teams']],
            'require' => ['concise-dot-digital/teams' => '@dev'],
        ]));
        $this->writeFile('composer.lock', '{"lock": true}');

        $this->app->setBasePath($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function writeFile(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($this->root.'/'.$path));
        File::put($this->root.'/'.$path, $contents);
    }

    private function contents(string $path): string
    {
        return File::get($this->root.'/'.$path);
    }

    /**
     * Fake composer and artisan, letting `php -l` run for real.
     *
     * @param  array<string, mixed>  $overrides  Command-line patterns tried first.
     */
    private function fakeProcesses(array $overrides = []): void
    {
        Process::fake([...$overrides, "'composer'*" => Process::result(), "*'artisan'*" => Process::result()]);
    }

    /** @param  list<string>  $command */
    private function assertProcessRan(array $command): void
    {
        Process::assertRan(fn (PendingProcess $process): bool => (array) $process->command === $command);
    }

    private function assertUntouched(): void
    {
        $this->assertSame(self::ROUTES, $this->contents('routes/web.php'));
        $this->assertSame(self::USER, $this->contents('app/Models/User.php'));
        $this->assertDirectoryExists($this->root.'/tests/Feature/Teams');
        $this->assertDirectoryExists($this->root.'/packages/teams');
    }

    public function test_it_strips_the_core_checks_it_loads_then_removes_the_tier(): void
    {
        $this->fakeProcesses();

        $this->artisan('bp:remove-teams', ['--force' => true])->assertSuccessful();

        $this->assertStringNotContainsString('teams:start', $this->contents('routes/web.php'));
        $this->assertStringContainsString("\$adminPath = 'admin';", $this->contents('routes/web.php'));
        $this->assertStringContainsString('    use HasRoles;', $this->contents('app/Models/User.php'));
        $this->assertDirectoryDoesNotExist($this->root.'/tests/Feature/Teams');
        $this->assertFileExists($this->root.'/tests/Unit/Support/Setup/MarkerStripperTest.php', 'only tests/Feature/Teams goes');
        $this->assertDirectoryDoesNotExist($this->root.'/packages/teams');

        // Caches cleared first so the check reads the stripped files; the repository goes through composer so the lock stays in step.
        $this->assertProcessRan([PHP_BINARY, 'artisan', 'optimize:clear', '--no-interaction']);
        $this->assertProcessRan([PHP_BINARY, 'artisan', 'route:list', '--no-interaction']);
        $this->assertProcessRan(['composer', 'config', '--unset', 'repositories.0']);
        $this->assertProcessRan(['composer', 'remove', 'concise-dot-digital/teams', '--no-interaction']);
    }

    public function test_the_load_check_runs_with_the_tier_undiscovered(): void
    {
        // The installed package's provider expects the seams just stripped, so the check must not boot it.
        $this->writeFile('vendor/composer/installed.json', json_encode(['packages' => [
            ['name' => 'concise-dot-digital/teams', 'extra' => ['laravel' => ['providers' => ['Concise\\Teams\\TeamsServiceProvider']]]],
            ['name' => 'acme/kept', 'extra' => ['laravel' => ['providers' => ['Acme\\KeptServiceProvider']]]],
        ]]));
        $discovered = null;
        $this->fakeProcesses(["*'route:list'*" => function (PendingProcess $process) use (&$discovered) {
            $discovered = require $process->environment['APP_PACKAGES_CACHE'];

            return Process::result();
        }]);

        $this->artisan('bp:remove-teams', ['--force' => true])->assertSuccessful();

        $this->assertSame(['acme/kept'], array_keys($discovered));
        $this->assertFileDoesNotExist($this->root.'/bootstrap/cache/packages.php', 'the real cache is left alone');
    }

    public function test_only_real_fences_are_stripped(): void
    {
        $this->fakeProcesses();

        $this->artisan('bp:remove-teams', ['--dry-run' => true])
            ->expectsOutputToContain('routes/web.php')
            ->doesntExpectOutputToContain('app/Plain.php')
            ->assertSuccessful();
    }

    public function test_when_the_stripped_app_wont_load_everything_is_put_back(): void
    {
        $this->fakeProcesses(["*'route:list'*" => Process::result(errorOutput: 'Undefined variable $apexHost', exitCode: 1)]);

        $this->artisan('bp:remove-teams', ['--force' => true])
            ->expectsOutputToContain('Undefined variable $apexHost')
            ->assertFailed();

        $this->assertUntouched();
        Process::assertDidntRun(fn (PendingProcess $process): bool => ((array) $process->command)[0] === 'composer');
    }

    public function test_a_check_that_throws_still_puts_everything_back(): void
    {
        $this->fakeProcesses();
        $this->writeFile('vendor/composer/installed.json', '{"packages": [{"no-name": true}]}');

        $this->artisan('bp:remove-teams', ['--force' => true])
            ->expectsOutputToContain('the check itself failed')
            ->assertFailed();

        $this->assertUntouched();
    }

    public function test_a_stripped_file_that_does_not_parse_is_put_back(): void
    {
        $this->fakeProcesses();
        $broken = "<?php\nif (true) {\n// teams:start\n}\n// teams:end\n";
        $this->writeFile('app/Unbalanced.php', $broken);

        $this->artisan('bp:remove-teams', ['--force' => true])
            ->expectsOutputToContain('app/Unbalanced.php: ')
            ->assertFailed();

        $this->assertUntouched();
        $this->assertSame($broken, $this->contents('app/Unbalanced.php'));
    }

    public function test_a_stripped_file_that_still_needs_the_tier_is_put_back(): void
    {
        $this->fakeProcesses();
        $this->writeFile('app/Leftover.php', "<?php\nuse Concise\\Teams\\Models\\Team;\n// teams:start\n// teams:end\n");

        $this->artisan('bp:remove-teams', ['--force' => true])
            ->expectsOutputToContain('app/Leftover.php: still needs the tier')
            ->assertFailed();

        $this->assertUntouched();
        $this->assertStringContainsString('teams:start', $this->contents('app/Leftover.php'));
    }

    public function test_an_unterminated_fence_changes_nothing_and_names_the_file(): void
    {
        $this->fakeProcesses();
        $this->writeFile('app/Broken.php', "<?php\n// teams:start\n\$kept = true;\n");

        $this->artisan('bp:remove-teams', ['--force' => true])
            ->expectsOutputToContain('app/Broken.php: Unterminated teams:start')
            ->assertFailed();

        $this->assertUntouched();
        Process::assertNothingRan();
    }

    public function test_a_failed_composer_remove_puts_the_core_and_composer_files_back(): void
    {
        $composerJson = $this->contents('composer.json');
        $this->fakeProcesses(["'composer' 'remove'*" => function () {
            File::put($this->root.'/composer.json', '{"half": "written"}');
            File::put($this->root.'/composer.lock', '{"half": "written"}');

            return Process::result(exitCode: 1);
        }]);

        $this->artisan('bp:remove-teams', ['--force' => true])
            ->expectsOutputToContain('composer install')
            ->assertFailed();

        $this->assertUntouched();
        $this->assertSame($composerJson, $this->contents('composer.json'));
        $this->assertSame('{"lock": true}', $this->contents('composer.lock'));
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->fakeProcesses();

        $this->artisan('bp:remove-teams', ['--dry-run' => true])->assertSuccessful();

        $this->assertUntouched();
        Process::assertNothingRan();
    }
}
