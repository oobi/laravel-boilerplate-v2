<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\TransformsSourceFiles;
use App\Support\Setup\MarkerStripper;
use App\Support\Teams\TierReferences;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Turn the boilerplate vanilla by removing the teams tier: the one-shot,
 * setup-time transform documented in docs/teams-distribution.md. It consumes
 * the `teams:start`/`teams:end` markers, so it stays correct as the seams
 * evolve rather than hardcoding line numbers.
 *
 * Destructive, so it checks before it destroys: the stripped core files must
 * lint, reference nothing from the tier and load the routes, or the originals
 * are put back. Only once `composer remove` succeeds does it delete anything,
 * and packages/teams goes last. Any failure exits non-zero, which bp:setup's
 * Vanilla recipe checks.
 */
class RemoveTeamsCommand extends Command
{
    use TransformsSourceFiles;

    protected $signature = 'bp:remove-teams {--dry-run : Show what would change without touching anything} {--force : Skip the confirmation prompt}';

    protected $description = 'Remove the teams tier and make the boilerplate vanilla.';

    /** Core directories that may carry fences. */
    public const ROOTS = ['app', 'bootstrap', 'config', 'database', 'routes'];

    private const PACKAGE = 'concise-dot-digital/teams';

    private const PACKAGE_PATH = 'packages/teams';

    private const TESTS = 'tests/Feature/Teams';

    public function handle(MarkerStripper $stripper, TierReferences $references): int
    {
        if (! File::isDirectory(base_path(self::PACKAGE_PATH))) {
            info('The teams tier is not installed: nothing to remove.');

            return self::SUCCESS;
        }

        $originals = $stripper->fencedFiles(collect(self::ROOTS)->map(fn (string $root): string => base_path($root))->all());

        try {
            $stripped = collect($originals)->map(fn (string $contents, string $file): string => $this->strip($stripper, $file, $contents))->all();
        } catch (RuntimeException $e) {
            error('Nothing changed: '.$e->getMessage());

            return self::FAILURE;
        }

        info('bp:remove-teams will:');
        $this->line('  • strip the teams markers from '.count($stripped).' core file(s), then check the app still loads');
        $this->line('  • drop the '.self::PACKAGE_PATH.' repository and run `composer remove '.self::PACKAGE.'`');
        $this->line('  • delete '.self::TESTS.' and '.self::PACKAGE_PATH);

        if ($this->option('dry-run')) {
            $this->newLine();
            info('Dry run: no changes made. Files to strip:');
            collect($stripped)->keys()->each(fn (string $file) => $this->line('    '.$this->relative($file)));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm('This permanently removes the teams tier from this project. Continue?', default: false)) {
            warning('Aborted: nothing changed.');

            return self::SUCCESS;
        }

        // Cached routes or config would be read instead of the stripped files, and would still name the tier.
        if (! $this->runArtisan('optimize:clear')) {
            error('Nothing changed: php artisan optimize:clear failed.');

            return self::FAILURE;
        }

        $this->write($stripped);

        try {
            $problems = $this->verify($stripped, $references);
        } catch (Throwable $e) {
            $problems = ['the check itself failed: '.$e->getMessage()];
        }

        if ($problems !== []) {
            $this->write($originals);
            error('The stripped app would not load, so every file was put back. Nothing else changed.');
            collect($problems)->each(fn (string $problem) => $this->line('  '.$problem));

            return self::FAILURE;
        }

        $composerFiles = $this->read([base_path('composer.json'), base_path('composer.lock')]);

        if (! $this->dropPathRepository() || ! $this->composer(['remove', self::PACKAGE, '--no-interaction'])) {
            $this->write($originals + $composerFiles);
            error('Removing the package failed, so the core files, composer.json and composer.lock were put back.');
            $this->line('  Run `composer install` to bring vendor/ back in line, then try again.');

            return self::FAILURE;
        }

        File::deleteDirectory(base_path(self::TESTS));
        File::deleteDirectory(base_path(self::PACKAGE_PATH));

        $this->newLine();
        info('Teams tier removed: the boilerplate is now vanilla.');
        $this->line('Next:');
        $this->line('  • npm install && npm run build   (a fresh tree needs the Vite manifest)');
        $this->line('  • php artisan test               (VanillaAppTest is the contract)');

        return self::SUCCESS;
    }

    /**
     * Why the stripped files wouldn't work without the tier, if anything.
     *
     * @param  array<string, string>  $stripped
     * @return list<string>
     */
    private function verify(array $stripped, TierReferences $references): array
    {
        $problems = collect($stripped)
            ->flatMap(function (string $contents, string $file) use ($references): array {
                return collect([$this->lint($file)])
                    ->filter()
                    ->merge(collect($references->in($contents))->map(fn (string $reference): string => 'still needs the tier at '.$reference))
                    ->map(fn (string $problem): string => $this->relative($file).': '.$problem)
                    ->all();
            })
            ->values()
            ->all();

        if ($problems === [] && ! $this->routesLoadWithoutTheTier($output)) {
            $problems[] = 'php artisan route:list: '.$output;
        }

        return $problems;
    }

    /**
     * Load the routes with the package still installed but not discovered: its
     * provider expects the core seams that were just stripped, so booting it
     * would fail even when the stripped core is fine. Its classes still
     * autoload, so this proves the core loads without the tier's provider;
     * TierReferences covers its code. Temporary package and services caches
     * keep the real ones untouched.
     */
    private function routesLoadWithoutTheTier(?string &$output): bool
    {
        $packages = tempnam(sys_get_temp_dir(), 'packages').'.php';
        $services = tempnam(sys_get_temp_dir(), 'services').'.php';

        try {
            (new PackageManifest(new Filesystem, base_path(), $packages))->build();
            $manifest = collect(require $packages)->except(self::PACKAGE)->all();
            File::put($packages, '<?php return '.var_export($manifest, true).';'.PHP_EOL);

            return $this->runArtisan('route:list', $output, ['APP_PACKAGES_CACHE' => $packages, 'APP_SERVICES_CACHE' => $services]);
        } finally {
            File::delete([$packages, $services, Str::beforeLast($packages, '.php'), Str::beforeLast($services, '.php')]);
        }
    }

    /** Drop the package's path repository through composer, so the lock's hash stays in step. */
    private function dropPathRepository(): bool
    {
        $repositories = json_decode(File::get(base_path('composer.json')), true)['repositories'] ?? [];
        $key = collect(is_array($repositories) ? $repositories : [])
            ->search(fn ($repository): bool => is_array($repository) && ($repository['url'] ?? null) === self::PACKAGE_PATH);

        return $key === false || $this->composer(['config', '--unset', 'repositories.'.$key]);
    }

    /** @param  list<string>  $args */
    private function composer(array $args): bool
    {
        $result = Process::path(base_path())
            ->timeout(300)
            ->run(['composer', ...$args], fn (string $type, string $buffer) => $this->output->write($buffer));

        if ($result->failed()) {
            warning('composer '.implode(' ', $args).' did not complete: see above.');

            return false;
        }

        return true;
    }
}
