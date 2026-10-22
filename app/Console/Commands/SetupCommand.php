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
 * First-run wizard for a fresh clone: asks the key shaping questions (app
 * name, the recipe from docs/config-recipes.md, optional teams relabelling)
 * and writes them to `.env`. Without the teams tier (the Vanilla recipe, or
 * Backoffice when told to drop it) it hands off to bp:remove-teams; for the
 * login-only Backoffice recipe it hands off to bp:make-login-only. It also
 * generates the app key if one is missing.
 *
 * It never runs your database or creates users: those stay explicit commands,
 * printed as the finishing steps.
 *
 * Interactive by default; `--recipe=` pre-answers the shape, `--without-teams`
 * drops the tier from Backoffice, and `--force` runs non-interactively (keeps
 * the current app name and the teams tier, no prompts).
 */
class SetupCommand extends Command
{
    protected $signature = 'bp:setup {--recipe= : public-saas|invitation-only|backoffice|vanilla} {--without-teams : Backoffice without the teams tier} {--dry-run} {--force}';

    protected $description = 'Configure a fresh clone for a supported app recipe (interactive).';

    /**
     * `teams`: whether the recipe keeps the tier (true), drops it (false) or
     * leaves it to the operator ('optional', Backoffice).
     *
     * @var array<string, array{label: string, creation?: string, members?: bool, admins?: bool, fallback: string, teams: bool|'optional', login_only?: bool}>
     */
    private const RECIPES = [
        'public-saas' => ['label' => 'Public SaaS: anyone signs up and creates their own team', 'creation' => 'self-service', 'members' => true, 'admins' => true, 'fallback' => 'home', 'teams' => true],
        'invitation-only' => ['label' => 'Invitation-only: admins seed teams, members invite', 'creation' => 'admin-only', 'members' => true, 'admins' => true, 'fallback' => 'home', 'teams' => true],
        'backoffice' => ['label' => 'Backoffice: login-only, admins create every account', 'creation' => 'admin-only', 'members' => false, 'admins' => false, 'fallback' => 'reject', 'teams' => 'optional', 'login_only' => true],
        'vanilla' => ['label' => 'Vanilla: no teams tier', 'fallback' => 'home', 'teams' => false],
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
        $teamsInstalled = File::isDirectory(base_path('packages/teams'));

        if ($config['teams'] === true && $this->option('without-teams')) {
            $this->error('The '.$recipe.' recipe needs the teams tier, so --without-teams doesn\'t apply. It is for the backoffice recipe.');

            return self::FAILURE;
        }

        if ($config['teams'] === true && ! $teamsInstalled) {
            $this->error('The '.$recipe.' recipe needs the teams tier, which this app no longer has.');

            return self::FAILURE;
        }

        $this->askApplication();
        $withTeams = $teamsInstalled && $this->keepsTeams($config);
        $this->collectRecipe($config, $withTeams);
        $removeTeams = $teamsInstalled && ! $withTeams;
        $loginOnly = $config['login_only'] ?? false;

        if ($this->option('dry-run')) {
            info('Dry run — would set in .env:');
            collect($this->env)->each(fn ($v, $k) => $this->line('  '.$k.'='.(is_bool($v) ? ($v ? 'true' : 'false') : $v)));

            if ($removeTeams) {
                $this->line('  …and run bp:remove-teams.');
            }

            if ($loginOnly) {
                $this->line('  …and run bp:make-login-only.');
            }
        } else {
            if (! $this->option('force') && ! confirm('Write this configuration to .env?', default: true)) {
                warning('Aborted — nothing changed.');

                return self::SUCCESS;
            }

            File::put(base_path('.env'), (new EnvEditor)->apply(File::get(base_path('.env')), $this->env));
            info('.env configured for the '.$recipe.' recipe.');

            if ($removeTeams && $this->call('bp:remove-teams', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }

            if ($loginOnly && $this->call('bp:make-login-only', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }

            $this->ensureAppKey();
        }

        // Finishing steps print on both paths, so a dry run previews the whole picture.
        $this->nextSteps($removeTeams);

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

    /**
     * Whether an installed teams tier stays, for a recipe that leaves it optional.
     *
     * @param  array{teams: bool|'optional'}  $config
     */
    private function keepsTeams(array $config): bool
    {
        if ($config['teams'] !== 'optional') {
            return $config['teams'];
        }

        if ($this->option('without-teams')) {
            return false;
        }

        return $this->option('force') || confirm('Keep the teams tier? (No removes it with bp:remove-teams.)', default: true);
    }

    /** @param array{creation?: string, members?: bool, admins?: bool, fallback: string} $config */
    private function collectRecipe(array $config, bool $withTeams): void
    {
        $this->env['LOGIN_FALLBACK'] = $config['fallback'];

        if (! $withTeams) {
            return; // no teams keys; bp:remove-teams handles the rest
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

    private function nextSteps(bool $removedTeams): void
    {
        $this->newLine();
        info('To finish setup, run:');
        $this->line('  • npm install && npm run build   (build the front-end assets)');
        $this->line($removedTeams
            ? '  • php artisan migrate:fresh --seed  (rebuild the database without the teams tables)'
            : '  • php artisan migrate --seed     (create and seed the database)');
        $this->line('  • php artisan bp:make-admin      (create the first admin user, a super admin)');
    }
}
