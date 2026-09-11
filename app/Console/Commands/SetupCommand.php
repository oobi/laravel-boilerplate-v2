<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Setup\EnvEditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * First-run wizard for a fresh clone: asks the key setup questions (app
 * identity, database, and the teams tier recipe — see docs/config-recipes.md),
 * writes the answers to `.env`, and offers to finalise (app key, migrate+seed,
 * first admin) so the tree is ready to roll. For the vanilla recipe it hands
 * off to bp:remove-teams. The structural code choices a login-only app needs
 * (public registration, public landing) can't be auto-applied safely yet, so
 * it prints exactly what to do.
 *
 * Interactive by default; `--recipe=` pre-answers the shape and `--force` runs
 * non-interactively (keeps current app/db settings, skips the finalise steps).
 */
class SetupCommand extends Command
{
    protected $signature = 'bp:setup {--recipe= : public-saas|invitation-only|backoffice|vanilla} {--dry-run} {--force}';

    protected $description = 'Configure a fresh clone for a supported app recipe (interactive).';

    /**
     * @var array<string, array{label: string, creation?: string, members?: bool, admins?: bool, fallback: string, teams?: bool, registration?: bool, landing?: bool}>
     */
    private const RECIPES = [
        'public-saas' => ['label' => 'Public SaaS — anyone signs up and creates their own team', 'creation' => 'self-service', 'members' => true, 'admins' => true, 'fallback' => 'home'],
        'invitation-only' => ['label' => 'Invitation-only — admins seed teams, members invite', 'creation' => 'admin-only', 'members' => true, 'admins' => true, 'fallback' => 'home'],
        'backoffice' => ['label' => 'Backoffice — admins create everything, no public surface', 'creation' => 'admin-only', 'members' => false, 'admins' => false, 'fallback' => 'reject', 'registration' => false, 'landing' => false],
        'vanilla' => ['label' => 'Vanilla — no teams tier', 'fallback' => 'home', 'teams' => false],
    ];

    /** @var array<string, string|bool> */
    private array $env = [];

    public function handle(): int
    {
        $recipe = $this->resolveRecipe();

        if ($recipe === null) {
            return self::FAILURE;
        }

        $config = self::RECIPES[$recipe];

        $this->askApplication();
        $this->askDatabase();
        $this->collectRecipe($config);

        if ($this->option('dry-run')) {
            info('Dry run — would set in .env:');
            collect($this->env)->each(fn ($v, $k) => $this->line('  '.$k.'='.(is_bool($v) ? ($v ? 'true' : 'false') : $v)));

            if (($config['teams'] ?? true) === false) {
                $this->line('  …and run bp:remove-teams.');
            }

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm('Write this configuration to .env?', default: true)) {
            warning('Aborted — nothing changed.');

            return self::SUCCESS;
        }

        File::put(base_path('.env'), (new EnvEditor)->apply(File::get(base_path('.env')), $this->env));
        info('.env configured for the '.$recipe.' recipe.');

        if (($config['teams'] ?? true) === false && $this->call('bp:remove-teams', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->ensureAppKey();
        $this->finalise();
        $this->structuralGuidance($config);
        $this->readyToRoll();

        return self::SUCCESS;
    }

    private function resolveRecipe(): ?string
    {
        $recipe = $this->option('recipe') ?? select(
            'Which app shape are you building?',
            collect(self::RECIPES)->map(fn (array $r): string => $r['label'])->all(),
        );

        if (! isset(self::RECIPES[$recipe])) {
            $this->error('Unknown recipe "'.$recipe.'". One of: '.implode(', ', array_keys(self::RECIPES)).'.');

            return null;
        }

        return $recipe;
    }

    private function askApplication(): void
    {
        if ($this->option('force')) {
            return;
        }

        $this->env['APP_NAME'] = text('Application name', default: (string) config('app.name'), required: true);
        $this->env['APP_URL'] = text('Application URL', default: (string) config('app.url'));
    }

    private function askDatabase(): void
    {
        if ($this->option('force')) {
            return;
        }

        if (! confirm('Use SQLite for local development? (quick start — otherwise keep your current DB_* settings)', default: true)) {
            return;
        }

        $sqlite = database_path('database.sqlite');
        if (! File::exists($sqlite)) {
            File::put($sqlite, '');
        }

        $this->env['DB_CONNECTION'] = 'sqlite';
        $this->env['DB_DATABASE'] = $sqlite;
    }

    /** @param array{creation?: string, members?: bool, admins?: bool, fallback: string, teams?: bool} $config */
    private function collectRecipe(array $config): void
    {
        $this->env['LOGIN_FALLBACK'] = $config['fallback'];

        if (($config['teams'] ?? true) === false) {
            return; // vanilla: no teams keys; bp:remove-teams handles the rest
        }

        $this->env['TEAMS_CREATION'] = $config['creation'];
        $this->env['TEAMS_MEMBER_INVITATIONS'] = $config['members'];
        $this->env['TEAMS_ADMIN_INVITATIONS'] = $config['admins'];

        $this->collectLabels();
    }

    private function collectLabels(): void
    {
        if ($this->option('force') || ! confirm('Relabel the tier\'s words (team / member / owner)?', default: false)) {
            return;
        }

        foreach (['TEAMS' => 'Team', 'TEAMS_MEMBER' => 'Member', 'TEAMS_OWNER' => 'Owner'] as $prefix => $noun) {
            $plural = $noun === 'Team' ? 'Teams' : ($noun === 'Member' ? 'Members' : 'Owners');
            $this->env[$prefix.'_LABEL_SINGULAR'] = text($noun.' — singular', default: $noun, required: true);
            $this->env[$prefix.'_LABEL_PLURAL'] = text($noun.' — plural', default: $plural, required: true);
        }
    }

    private function ensureAppKey(): void
    {
        if (config('app.key')) {
            return;
        }

        $this->call('key:generate', ['--force' => true]);
    }

    private function finalise(): void
    {
        if ($this->option('force')) {
            return;
        }

        if (confirm('Run database migrations and seed now?', default: true)) {
            $this->call('migrate', ['--seed' => true, '--force' => true]);
        }

        if (confirm('Create the first admin user now?', default: true)) {
            $this->call('bp:make-admin');
        }
    }

    /** @param array{registration?: bool, landing?: bool} $config */
    private function structuralGuidance(array $config): void
    {
        if (($config['registration'] ?? true) && ($config['landing'] ?? true)) {
            return;
        }

        $this->newLine();
        warning('This recipe is login-only. Two structural edits are yours to make (one-time, see docs/config-recipes.md):');

        if (($config['registration'] ?? true) === false) {
            $this->line('  • Disable public registration — remove `Features::registration()` from config/fortify.php.');
        }

        if (($config['landing'] ?? true) === false) {
            $this->line('  • Drop the public landing page — point the `/` route (routes/web.php) at login instead of the Home page.');
        }
    }

    private function readyToRoll(): void
    {
        $this->newLine();
        $this->line('Ready to roll — remaining setup steps if you skipped them:');
        $this->line('  • npm install && npm run build   (Vite manifest)');
        $this->line('  • php artisan migrate --seed');
        $this->line('  • php artisan bp:make-admin');
    }
}
