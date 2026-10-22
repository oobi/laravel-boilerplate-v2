<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\TransformsSourceFiles;
use App\Support\Setup\MarkerStripper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Make the app login-only: no public landing page and no public sign-up, the
 * structural half of the Backoffice recipe (docs/config-recipes.md). A
 * one-shot, setup-time transform like bp:remove-teams. It consumes the
 * `public:start`/`public:end` markers (the `/` route becomes
 * LoginOnlyHomeController, Features::registration() goes) and deletes the files
 * only the public surface uses.
 *
 * The app must still load afterwards, or every file is put back. bp:setup's
 * Backoffice recipe runs it; running it again changes nothing.
 */
class MakeLoginOnlyCommand extends Command
{
    use TransformsSourceFiles;

    protected $signature = 'bp:make-login-only {--dry-run : Show what would change without touching anything} {--force : Skip the confirmation prompt}';

    protected $description = 'Remove the public landing page and public sign-up, making the app login-only.';

    public const MARKER = 'public';

    /** Core directories that may carry fences. */
    public const ROOTS = ['app', 'bootstrap', 'config', 'database', 'routes'];

    /** Files only the public landing page and sign-up use, deleted with them. */
    public const PUBLIC_FILES = [
        'app/Livewire/Home.php',
        'resources/views/livewire/home.blade.php',
        'resources/views/layouts/public.blade.php',
        'resources/views/layouts/partials/public-nav.blade.php',
        'resources/views/auth/register.blade.php',
        'tests/Feature/HomeTest.php',
        'tests/Feature/Auth/RegistrationTest.php',
    ];

    public function handle(): int
    {
        $stripper = new MarkerStripper(self::MARKER);
        $fenced = $stripper->fencedFiles(collect(self::ROOTS)->map(fn (string $root): string => base_path($root))->all());
        $public = $this->read(collect(self::PUBLIC_FILES)->map(fn (string $path): string => base_path($path))->all());

        if ($fenced === [] && $public === []) {
            info('The app is already login-only: nothing to change.');

            return self::SUCCESS;
        }

        try {
            $stripped = collect($fenced)->map(fn (string $contents, string $file): string => $this->strip($stripper, $file, $contents))->all();
        } catch (RuntimeException $e) {
            error('Nothing changed: '.$e->getMessage());

            return self::FAILURE;
        }

        info('bp:make-login-only will:');
        $this->line('  • strip the public markers from '.count($stripped).' core file(s): `/` sends guests to login, public sign-up is switched off');
        $this->line('  • delete the '.count($public).' file(s) only the public landing page and sign-up use, then check the app still loads');

        if ($this->option('dry-run')) {
            $this->newLine();
            info('Dry run: no changes made.');
            $this->line('  Files to strip:');
            collect($stripped)->keys()->each(fn (string $file) => $this->line('    '.$this->relative($file)));
            $this->line('  Files to delete:');
            collect($public)->keys()->each(fn (string $file) => $this->line('    '.$this->relative($file)));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm('This permanently removes the public landing page and sign-up. Continue?', default: false)) {
            warning('Aborted: nothing changed.');

            return self::SUCCESS;
        }

        // Cached routes or config would be read instead of the stripped files.
        if (! $this->runArtisan('optimize:clear')) {
            error('Nothing changed: php artisan optimize:clear failed.');

            return self::FAILURE;
        }

        $this->write($stripped);
        File::delete(array_keys($public));

        try {
            $problems = $this->verify($stripped);
        } catch (Throwable $e) {
            $problems = ['the check itself failed: '.$e->getMessage()];
        }

        if ($problems !== []) {
            $this->write($fenced + $public);
            error('The login-only app would not load, so every file was put back.');
            collect($problems)->each(fn (string $problem) => $this->line('  '.$problem));

            return self::FAILURE;
        }

        $this->newLine();
        info('The app is now login-only: `/` sends guests to login and public sign-up is off.');

        return self::SUCCESS;
    }

    /**
     * Why the login-only app wouldn't work, if anything.
     *
     * @param  array<string, string>  $stripped
     * @return list<string>
     */
    private function verify(array $stripped): array
    {
        $problems = collect($stripped)
            ->keys()
            ->map(fn (string $file): ?string => ($error = $this->lint($file)) === null ? null : $this->relative($file).': '.$error)
            ->filter()
            ->values()
            ->all();

        if ($problems === [] && ! $this->runArtisan('route:list', $output)) {
            $problems[] = 'php artisan route:list: '.$output;
        }

        return $problems;
    }
}
