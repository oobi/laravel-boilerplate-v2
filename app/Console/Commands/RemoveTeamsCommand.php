<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Teams\MarkerStripper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Turn the boilerplate vanilla by removing the teams tier — the one-shot,
 * setup-time transform documented in docs/teams-distribution.md. It consumes
 * the `teams:start`/`teams:end` markers, so it stays correct as the seams
 * evolve rather than hardcoding line numbers. Destructive: prints its plan and
 * confirms first, and supports --dry-run.
 */
class RemoveTeamsCommand extends Command
{
    protected $signature = 'bp:remove-teams {--dry-run : Show what would change without touching anything} {--force : Skip the confirmation prompt}';

    protected $description = 'Remove the teams tier and make the boilerplate vanilla.';

    private const PACKAGE = 'concise-dot-digital/teams';

    public function handle(): int
    {
        if (! File::isDirectory(base_path('packages/teams'))) {
            info('The teams tier is not installed — nothing to remove.');

            return self::SUCCESS;
        }

        $fenced = $this->filesContaining([app_path(), database_path(), config_path(), base_path('routes'), base_path('bootstrap')], 'teams:'.'start');
        $tests = $this->filesContaining([base_path('tests')], 'Concise\\Teams');

        info('bp:remove-teams will:');
        $this->line('  • strip the teams markers from '.count($fenced).' core file(s)');
        $this->line('  • delete '.count($tests).' teams-coupled test file(s)');
        $this->line('  • run `composer remove '.self::PACKAGE.'` and drop its path repository');
        $this->line('  • delete packages/teams');

        if ($this->option('dry-run')) {
            $this->newLine();
            info('Dry run — no changes made.');
            collect($fenced)->merge($tests)->each(fn (string $f) => $this->line('    '.$this->relative($f)));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm('This permanently removes the teams tier from this project. Continue?', default: false)) {
            warning('Aborted — nothing changed.');

            return self::SUCCESS;
        }

        $stripper = new MarkerStripper;
        foreach ($fenced as $file) {
            File::put($file, $stripper->strip(File::get($file)));
        }

        foreach ($tests as $file) {
            File::delete($file);
        }

        $composerOk = $this->composer(['remove', self::PACKAGE, '--no-interaction']);
        $this->dropPathRepository('packages/teams');
        File::deleteDirectory(base_path('packages/teams'));
        if ($composerOk) {
            $this->composer(['dump-autoload']);
        }

        $this->newLine();
        info('Teams tier removed — the boilerplate is now vanilla.');
        $this->line('Next:');
        if (! $composerOk) {
            $this->line('  • composer could not be run here — run `composer remove '.self::PACKAGE.'` yourself');
        }
        $this->line('  • npm install && npm run build   (a fresh tree needs the Vite manifest)');
        $this->line('  • php artisan test               (VanillaAppTest is the contract)');
        $this->line('  • the teams-removal tooling (app/Support/Teams, this command) is now dead weight — delete it if you like');

        return self::SUCCESS;
    }

    /**
     * PHP files under $roots whose contents include $needle (literal, so a
     * namespace backslash is matched as written — unlike Finder::contains()).
     *
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function filesContaining(array $roots, string $needle): array
    {
        $roots = array_filter($roots, File::isDirectory(...));

        if ($roots === []) {
            return [];
        }

        return collect(Finder::create()->files()->in($roots)->name('*.php'))
            ->filter(fn ($file): bool => str_contains($file->getContents(), $needle))
            ->map(fn ($file): string => $file->getRealPath())
            ->values()
            ->all();
    }

    /** Remove a path repository (and any lingering require) for $url from composer.json. */
    private function dropPathRepository(string $url): void
    {
        $path = base_path('composer.json');
        $json = json_decode(File::get($path), true);

        if (! is_array($json)) {
            warning('Could not parse composer.json — remove the '.$url.' repository entry by hand.');

            return;
        }

        if (isset($json['repositories']) && is_array($json['repositories'])) {
            $json['repositories'] = array_values(array_filter(
                $json['repositories'],
                fn ($repo): bool => ! (is_array($repo) && ($repo['url'] ?? null) === $url),
            ));
        }

        unset($json['require'][self::PACKAGE]);

        File::put($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    /** @param  list<string>  $args */
    private function composer(array $args): bool
    {
        $process = new Process(['composer', ...$args], base_path(), timeout: 300);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            warning('composer '.implode(' ', $args).' did not complete — see above.');

            return false;
        }

        return true;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
