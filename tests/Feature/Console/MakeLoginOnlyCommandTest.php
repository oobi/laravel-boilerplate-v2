<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * bp:make-login-only run against a small scratch tree, never this repo: it
 * strips the public fences and deletes the public files, then checks the app
 * still loads, putting everything back if not. Artisan is faked; `php -l` runs
 * for real. The real fences are covered by LoginOnlyFencesTest.
 */
class MakeLoginOnlyCommandTest extends TestCase
{
    private string $root;

    private const ROUTES = <<<'PHP'
        <?php

        // public:start
        use App\Livewire\Home;
        // public:end

        // public:start
        // public:canonical: Route::get('/', LoginOnlyHomeController::class)->name('home');
        Route::get('/', Home::class)->name('home');
        // public:end
        PHP;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/make-login-only-'.uniqid();

        $this->writeFile('routes/web.php', self::ROUTES);
        $this->writeFile('app/Livewire/Home.php', '<?php');
        $this->writeFile('resources/views/layouts/public.blade.php', '<html>');

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

    private function assertUntouched(): void
    {
        $this->assertSame(self::ROUTES, File::get($this->root.'/routes/web.php'));
        $this->assertFileExists($this->root.'/app/Livewire/Home.php');
        $this->assertFileExists($this->root.'/resources/views/layouts/public.blade.php');
    }

    public function test_it_strips_the_fences_deletes_the_public_files_and_checks_the_app_loads(): void
    {
        Process::fake(["*'artisan'*" => Process::result()]);

        $this->artisan('bp:make-login-only', ['--force' => true])->assertSuccessful();

        $routes = File::get($this->root.'/routes/web.php');
        $this->assertStringContainsString("Route::get('/', LoginOnlyHomeController::class)->name('home');", $routes);
        $this->assertStringNotContainsString('Home::class', $routes);
        $this->assertStringNotContainsString('public:start', $routes);
        $this->assertFileDoesNotExist($this->root.'/app/Livewire/Home.php');
        $this->assertFileDoesNotExist($this->root.'/resources/views/layouts/public.blade.php');
        Process::assertRan(fn (PendingProcess $process): bool => (array) $process->command === [PHP_BINARY, 'artisan', 'route:list', '--no-interaction']);
    }

    public function test_when_the_app_wont_load_everything_is_put_back(): void
    {
        Process::fake([
            "*'route:list'*" => Process::result(errorOutput: 'View [layouts.public] not found.', exitCode: 1),
            "*'artisan'*" => Process::result(),
        ]);

        $this->artisan('bp:make-login-only', ['--force' => true])
            ->expectsOutputToContain('View [layouts.public] not found.')
            ->assertFailed();

        $this->assertUntouched();
    }

    public function test_an_unterminated_fence_changes_nothing_and_names_the_file(): void
    {
        Process::fake();
        $this->writeFile('config/fortify.php', "<?php\n// public:start\n\$kept = true;\n");

        $this->artisan('bp:make-login-only', ['--force' => true])
            ->expectsOutputToContain('config/fortify.php: Unterminated public:start')
            ->assertFailed();

        $this->assertUntouched();
        Process::assertNothingRan();
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        Process::fake();

        $this->artisan('bp:make-login-only', ['--dry-run' => true])
            ->expectsOutputToContain('app/Livewire/Home.php')
            ->assertSuccessful();

        $this->assertUntouched();
        Process::assertNothingRan();
    }

    public function test_an_app_that_is_already_login_only_is_left_alone(): void
    {
        Process::fake();
        File::deleteDirectory($this->root);
        $this->writeFile('routes/web.php', "<?php\n");

        $this->artisan('bp:make-login-only', ['--force' => true])
            ->expectsOutputToContain('already login-only')
            ->assertSuccessful();

        Process::assertNothingRan();
    }
}
