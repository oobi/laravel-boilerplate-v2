<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\MakeLoginOnlyCommand;
use App\Support\Setup\MarkerStripper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * The public surface's seams, checked against what bp:make-login-only relies
 * on: every fence strips to code that lints and loads (stripped into a scratch
 * copy, never in place), and nothing it keeps still uses a file it deletes.
 * Skipped once the app is login-only, when there is nothing left to check.
 */
class LoginOnlyFencesTest extends TestCase
{
    /** How the deleted files are referred to from elsewhere. */
    private const REFERENCES = ['layouts.public', 'layouts.partials.public-nav', 'livewire.home', 'App\Livewire\Home'];

    /** @return array<string, string> */
    private function fencedFiles(): array
    {
        $fenced = (new MarkerStripper(MakeLoginOnlyCommand::MARKER))
            ->fencedFiles(collect(MakeLoginOnlyCommand::ROOTS)->map(fn (string $root): string => base_path($root))->all());

        if ($fenced === []) {
            $this->markTestSkipped('The app is already login-only.');
        }

        return $fenced;
    }

    public function test_every_fence_strips_to_a_login_only_app_that_loads(): void
    {
        $stripped = collect($this->fencedFiles())
            ->mapWithKeys(fn (string $contents, string $file): array => [str_replace(base_path().'/', '', $file) => (new MarkerStripper(MakeLoginOnlyCommand::MARKER))->strip($contents)]);

        $this->assertStringNotContainsString('Features::registration()', $stripped['config/fortify.php']);
        $this->assertStringContainsString("Route::get('/', LoginOnlyHomeController::class)->name('home');", $stripped['routes/web.php']);

        $scratch = sys_get_temp_dir().'/login-only-'.uniqid();
        File::ensureDirectoryExists($scratch);

        try {
            foreach ($stripped as $path => $contents) {
                File::put($scratch.'/'.str_replace('/', '_', $path), $contents);

                $this->assertTrue(Process::run([PHP_BINARY, '-l', $scratch.'/'.str_replace('/', '_', $path)])->successful(), $path.' does not lint once stripped');
            }

            (static function (string $routes): void {
                require $routes;
            })($scratch.'/routes_web.php');
        } finally {
            File::deleteDirectory($scratch);
        }
    }

    public function test_nothing_kept_uses_a_deleted_file(): void
    {
        $fenced = $this->fencedFiles();
        $deleted = collect(MakeLoginOnlyCommand::PUBLIC_FILES)->map(fn (string $path): string => base_path($path));
        $deleted->each(fn (string $file) => $this->assertFileExists($file, 'bp:make-login-only lists a file that no longer exists'));

        $directories = collect(['app', 'bootstrap', 'config', 'database', 'resources/views', 'routes', 'tests'])->map(fn (string $dir): string => base_path($dir))->all();

        $users = collect(Finder::create()->files()->in($directories)->name(['*.php']))
            ->reject(fn (SplFileInfo $file): bool => $deleted->contains($file->getPathname()))
            ->map(fn (SplFileInfo $file): string => isset($fenced[$file->getPathname()])
                ? (new MarkerStripper(MakeLoginOnlyCommand::MARKER))->strip($fenced[$file->getPathname()])
                : $file->getContents())
            ->filter(fn (string $contents): bool => Str::contains($contents, self::REFERENCES))
            ->keys()
            ->reject(fn (string $file): bool => in_array($file, [__FILE__, __DIR__.'/MakeLoginOnlyCommandTest.php'], true))
            ->map(fn (string $file): string => str_replace(base_path().'/', '', $file))
            ->values()
            ->all();

        $this->assertSame([], $users, 'These would break once the public files are deleted');
    }
}
