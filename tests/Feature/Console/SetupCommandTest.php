<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * bp:setup run against a small scratch tree, never this repo, with composer
 * and artisan faked: the recipe's .env keys, and its hand-offs to
 * bp:remove-teams and bp:make-login-only (each covered by its own test).
 */
class SetupCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/setup-'.uniqid();

        $this->writeFile('.env', "APP_NAME=Laravel\n");
        $this->writeFile('composer.json', json_encode(['repositories' => [['type' => 'path', 'url' => 'packages/teams']]]));
        $this->writeFile('packages/teams/composer.json', '{}');
        $this->writeFile('app/Livewire/Home.php', '<?php');

        $this->app->setBasePath($this->root);
        Process::fake(["'composer'*" => Process::result(), "*'artisan'*" => Process::result()]);
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

    private function env(): string
    {
        return File::get($this->root.'/.env');
    }

    private function assertComposerRemoveRan(bool $ran): void
    {
        $removed = fn (PendingProcess $process): bool => (array) $process->command === ['composer', 'remove', 'concise-dot-digital/teams', '--no-interaction'];

        $ran ? Process::assertRan($removed) : Process::assertDidntRun($removed);
    }

    public function test_backoffice_keeps_the_tier_and_makes_the_app_login_only(): void
    {
        $this->artisan('bp:setup', ['--recipe' => 'backoffice', '--force' => true])->assertSuccessful();

        $this->assertStringContainsString('LOGIN_FALLBACK=reject', $this->env());
        $this->assertStringContainsString('TEAMS_CREATION=admin-only', $this->env());
        $this->assertStringContainsString('TEAMS_MEMBER_INVITATIONS=false', $this->env());
        $this->assertComposerRemoveRan(false);
        $this->assertDirectoryExists($this->root.'/packages/teams');
        $this->assertFileDoesNotExist($this->root.'/app/Livewire/Home.php', 'bp:make-login-only ran');
    }

    public function test_backoffice_without_teams_removes_the_tier_and_writes_no_teams_keys(): void
    {
        $this->artisan('bp:setup', ['--recipe' => 'backoffice', '--without-teams' => true, '--force' => true])->assertSuccessful();

        $this->assertStringContainsString('LOGIN_FALLBACK=reject', $this->env());
        $this->assertStringNotContainsString('TEAMS_', $this->env());
        $this->assertComposerRemoveRan(true);
        $this->assertDirectoryDoesNotExist($this->root.'/packages/teams');
        $this->assertFileDoesNotExist($this->root.'/app/Livewire/Home.php');
    }

    public function test_backoffice_asks_whether_to_keep_the_tier(): void
    {
        $this->artisan('bp:setup', ['--recipe' => 'backoffice'])
            ->expectsQuestion('Application name', 'Backoffice')
            ->expectsConfirmation('Keep the teams tier? (No removes it with bp:remove-teams.)', 'no')
            ->expectsConfirmation('Write this configuration to .env?', 'yes')
            ->assertSuccessful();

        $this->assertStringContainsString('APP_NAME=Backoffice', $this->env());
        $this->assertStringNotContainsString('TEAMS_', $this->env());
        $this->assertComposerRemoveRan(true);
    }

    public function test_backoffice_after_the_tier_is_gone_just_skips_it(): void
    {
        File::deleteDirectory($this->root.'/packages/teams');

        $this->artisan('bp:setup', ['--recipe' => 'backoffice', '--force' => true])->assertSuccessful();

        $this->assertStringContainsString('LOGIN_FALLBACK=reject', $this->env());
        $this->assertStringNotContainsString('TEAMS_', $this->env());
        $this->assertComposerRemoveRan(false);
        $this->assertFileDoesNotExist($this->root.'/app/Livewire/Home.php');
    }

    public function test_a_teams_recipe_without_the_tier_fails_and_changes_nothing(): void
    {
        File::deleteDirectory($this->root.'/packages/teams');

        $this->artisan('bp:setup', ['--recipe' => 'public-saas', '--force' => true])
            ->expectsOutputToContain('needs the teams tier')
            ->assertFailed();

        $this->assertSame("APP_NAME=Laravel\n", $this->env());
    }

    public function test_without_teams_is_refused_for_a_recipe_that_needs_the_tier(): void
    {
        $this->artisan('bp:setup', ['--recipe' => 'public-saas', '--without-teams' => true, '--force' => true])
            ->expectsOutputToContain("--without-teams doesn't apply")
            ->assertFailed();

        $this->assertSame("APP_NAME=Laravel\n", $this->env());
        $this->assertDirectoryExists($this->root.'/packages/teams');
    }

    public function test_a_public_recipe_keeps_the_public_surface(): void
    {
        $this->artisan('bp:setup', ['--recipe' => 'public-saas', '--force' => true])->assertSuccessful();

        $this->assertStringContainsString('LOGIN_FALLBACK=home', $this->env());
        $this->assertFileExists($this->root.'/app/Livewire/Home.php');
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->artisan('bp:setup', ['--recipe' => 'backoffice', '--without-teams' => true, '--dry-run' => true, '--force' => true])
            ->expectsOutputToContain('bp:remove-teams')
            ->expectsOutputToContain('bp:make-login-only')
            ->assertSuccessful();

        $this->assertSame("APP_NAME=Laravel\n", $this->env());
        $this->assertDirectoryExists($this->root.'/packages/teams');
        $this->assertFileExists($this->root.'/app/Livewire/Home.php');
        Process::assertNothingRan();
    }
}
