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
 * First-run wizard for a fresh clone: asks the key shaping questions — app
 * name, and the teams tier recipe with optional relabelling (see
 * docs/config-recipes.md) — and writes them to `.env`. For the vanilla recipe
 * it hands off to bp:remove-teams, and it generates the app key if one is
 * missing.
 *
 * It does what it safely can, then prints the finishing steps it leaves to you:
 * it never runs your database or creates users (those stay explicit commands).
 * The structural code choices a login-only app needs (public registration is a
 * Fortify edit, the public landing page a scaffold decision) can't be
 * auto-applied safely either, so it prints exactly what to do.
 *
 * Interactive by default; `--recipe=` pre-answers the shape and `--force` runs
 * non-interactively (keeps the current app name, no prompts).
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
        $this->collectRecipe($config);

        if ($this->option('dry-run')) {
            info('Dry run — would set in .env:');
            collect($this->env)->each(fn ($v, $k) => $this->line('  '.$k.'='.(is_bool($v) ? ($v ? 'true' : 'false') : $v)));

            if (($config['teams'] ?? true) === false) {
                $this->line('  …and run bp:remove-teams.');
            }
        } else {
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
        }

        // Guidance + finishing steps print on both paths, so a dry run previews the whole picture.
        $this->structuralGuidance($config);
        $this->nextSteps();

        return self::SUCCESS;
    }

    /** The one safe thing we can finish automatically — a fresh clone needs a key. */
    private function ensureAppKey(): void
    {
        if (config('app.key')) {
            return;
        }

        info('Generating the application key…');
        $this->call('key:generate', ['--force' => true]);
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

        foreach (['TEAMS' => ['Team', 'Teams'], 'TEAMS_MEMBER' => ['Member', 'Members'], 'TEAMS_OWNER' => ['Owner', 'Owners']] as $prefix => [$singular, $plural]) {
            $this->env[$prefix.'_LABEL_SINGULAR'] = text($singular.' — singular', default: $singular, required: true);
            $this->env[$prefix.'_LABEL_PLURAL'] = text($singular.' — plural', default: $plural, required: true);
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
            $this->line('  • Disable public registration — remove `Features::registration()` from config/fortify.php (and any "Register" links in the auth/welcome views).');
        }

        if (($config['landing'] ?? true) === false) {
            $this->line('  • Drop the public landing page — point the `/` route (routes/web.php) at login instead of the Home page.');
        }
    }

    private function nextSteps(): void
    {
        $this->newLine();
        info('To finish setup, run:');
        $this->line('  • npm install && npm run build   (build the front-end assets)');
        $this->line('  • php artisan migrate --seed     (create and seed the database)');
        $this->line('  • php artisan bp:make-admin      (create the first admin user)');
    }
}
