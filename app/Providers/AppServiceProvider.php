<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Enums\UserAbility;
use App\Http\Middleware\DenyLowAssuranceSessions;
use App\Http\Middleware\KeepMandatedTwoFactor;
use App\Http\Middleware\LeaveImpersonationOnLogout;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Models\User;
use App\Observers\UserObserver;
use App\Support\Auth\MailedLinks;
use App\Support\Auth\PasswordChecks;
use App\Support\Roles\AdminRoleScopes;
use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesIconAlias;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuthorization();
        $this->registerFortify();

        // Livewire re-runs only its persistent middleware (auth, `can:`, bindings)
        // on an update request; our own route middleware has to be added.
        Livewire::addPersistentMiddleware(DenyLowAssuranceSessions::class);
        $this->registerFilamentIcons();
        $this->registerFilamentModalDefaults();
        $this->registerFilamentBulkActionDefaults();
        $this->registerFilamentRestoreDefaults();
        $this->registerNotifications();
        $this->registerQueryMacros();

        // One password rule for every path that sets a password (#41); see config/auth.php.
        Password::defaults(fn (): Password => Password::min(config()->integer('auth.password_min_length')));

        AdminRoleScopes::define();

        User::observe(UserObserver::class);
    }

    /**
     * Keep every Filament modal footer consistent with the app's own
     * <x-modal> / <x-button.cancel> conventions:
     *
     * - The cancel action renders as a neutral outline button (Filament's
     *   default gray cancel is a solid fill), matching <x-button.cancel> so
     *   every "cancel" looks identical whether the modal is Filament- or
     *   Blade-rendered. The internal modal dismiss action is always named
     *   `cancel` (Filament's makeModalAction('cancel')).
     * - Footer buttons follow the house order — cancel on the left, the
     *   affirmative action on the right. Confirmation modals keep Filament's
     *   centered footer (already cancel-left / action-right); form modals move
     *   from Filament's default left alignment to End, which pairs with the
     *   `fi-align-end` flex-row-reverse footer to land cancel-left /
     *   action-right, matching <x-modal>'s right-aligned footer.
     * - On long form/content modals the header (title + close) and footer
     *   (buttons) stick, so they stay in view while only the content scrolls —
     *   matters most on mobile. A short confirmation dialog never scrolls, and
     *   sticky adds a header/footer divider that makes it look busier than a
     *   daisyUI confirmation — so it stays non-sticky (and divider-free). The
     *   sticky condition is a closure because `isConfirmationRequired()` isn't
     *   known yet when this configureUsing callback runs (it's set later, by
     *   the action's own `->requiresConfirmation()`).
     */
    private function registerFilamentModalDefaults(): void
    {
        Action::configureUsing(function (Action $action): void {
            if ($action->getName() === 'cancel') {
                $action->outlined();
            }

            $action
                ->stickyModalHeader(fn (): bool => ! $action->isConfirmationRequired())
                ->stickyModalFooter(fn (): bool => ! $action->isConfirmationRequired())
                ->modalFooterActionsAlignment(
                    fn (): Alignment => $action->isConfirmationRequired() ? Alignment::Center : Alignment::End,
                );
        });
    }

    /**
     * Every table's bulk-action toolbar renders its trigger as a pale,
     * menu-like outline button (Filament's default outlined look, not the
     * strong neutral outline) so it reads as a "menu" rather than a solid
     * button — see `.fi-btn-menu` in filament-buttons.css. Applied app-wide
     * so individual tables don't repeat the styling; a table can still
     * override any of it with its own fluent calls after make().
     */
    private function registerFilamentBulkActionDefaults(): void
    {
        BulkActionGroup::configureUsing(function (BulkActionGroup $group): void {
            $group
                ->button()
                ->outlined()
                ->extraAttributes(['class' => 'fi-btn-menu']);
        });
    }

    /**
     * Every RestoreAction and RestoreBulkAction, row or bulk, is success
     * coloured like Activate, matching the Users Show page's own Restore
     * action. A table can still override it with its own ->color().
     */
    private function registerFilamentRestoreDefaults(): void
    {
        RestoreAction::configureUsing(function (RestoreAction $action): void {
            $action->color(DaisyColor::SUCCESS->toFilamentColor());
        });

        RestoreBulkAction::configureUsing(function (RestoreBulkAction $action): void {
            $action->color(DaisyColor::SUCCESS->toFilamentColor());
        });
    }

    /** Top-center toasts instead of Filament's default top-right. */
    private function registerNotifications(): void
    {
        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::Start);

        // Laravel's defaults build these links from the request's host, which the
        // sender chooses (GitHub #58). Same routes, parameters and expiry, from APP_URL.
        ResetPassword::createUrlUsing(fn (CanResetPassword $user, string $token): string => MailedLinks::generator()->route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));

        VerifyEmail::createUrlUsing(fn (MustVerifyEmail $user): string => MailedLinks::generator()->temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));
    }

    /**
     * `whereAnyContains(['first_name', 'email'], $term)`: rows where any column
     * contains $term as typed, ignoring case. `%` and `_` in the term are
     * matched literally, not as LIKE wildcards (#40), with an explicit ESCAPE
     * because SQLite has no default escape character. Postgres's `like` is
     * case-sensitive, so it gets `ilike` on the column as text, as Laravel's
     * own whereLike does there.
     */
    private function registerQueryMacros(): void
    {
        QueryBuilder::macro('whereAnyContains', function (array $columns, string $term): QueryBuilder {
            /** @var QueryBuilder $this */
            $pattern = '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $isPostgres = $this->getConnection()->getDriverName() === 'pgsql';

            return $this->where(fn (QueryBuilder $query) => collect($columns)->each(function (string $column) use ($query, $pattern, $isPostgres): void {
                $wrapped = $query->getGrammar()->wrap($column);
                $query->orWhereRaw($isPostgres ? "{$wrapped}::text ilike ? escape '!'" : "{$wrapped} like ? escape '!'", [$pattern]);
            }));
        });
    }

    /** Sortable-but-unsorted columns get a double chevron; once sorted, a single chevron shows the direction (daisyUI style). */
    private function registerFilamentIcons(): void
    {
        FilamentIcon::register([
            TablesIconAlias::HEADER_CELL_SORT_BUTTON => Heroicon::ChevronUpDown,
            TablesIconAlias::HEADER_CELL_SORT_ASC_BUTTON => Heroicon::ChevronUp,
            TablesIconAlias::HEADER_CELL_SORT_DESC_BUTTON => Heroicon::ChevronDown,
        ]);
    }

    /**
     * Grants a super admin (a hardcoded flag, not a spatie role) every
     * ability app-wide — both plain SystemPermission-named checks (which
     * spatie/laravel-permission's own Gate::before resolves via
     * hasPermissionTo()) and per-instance UserPolicy abilities.
     * `impersonate`/`assignRole` are always excluded — both have a rule
     * ("target must not be a super admin", not just "not self") a simple
     * self-comparison can't express, so they always defer to UserPolicy's
     * own check even for a super admin actor. `delete`/`toggleActive`/
     * `grantSuperAdmin` and the credential abilities (`updatePasswordDirectly`,
     * `sendPasswordResetLink`, `verifyEmail`, `resetTwoFactorAuthentication`,
     * `manageTwoFactorGrace`, `unlinkGoogleAccount`) are excluded only when the target is the actor
     * themselves, so those still fall through to UserPolicy's self-check.
     */
    private function registerAuthorization(): void
    {
        Gate::before(function (User $actor, string $ability, array $arguments = []): ?bool {
            if (in_array($ability, [UserAbility::IMPERSONATE->value, UserAbility::ASSIGN_ROLE->value], true)) {
                return null;
            }

            if (! $actor->isSuperAdmin()) {
                return null;
            }

            $target = $arguments[0] ?? null;
            $isSelf = $target instanceof User && $target->id === $actor->id;

            if ($isSelf && in_array($ability, [UserAbility::DELETE->value, UserAbility::TOGGLE_ACTIVE->value, UserAbility::GRANT_SUPER_ADMIN->value, UserAbility::UPDATE_PASSWORD_DIRECTLY->value, UserAbility::SEND_PASSWORD_RESET_LINK->value, UserAbility::VERIFY_EMAIL->value, UserAbility::RESET_TWO_FACTOR_AUTHENTICATION->value, UserAbility::MANAGE_TWO_FACTOR_GRACE->value, UserAbility::UNLINK_GOOGLE_ACCOUNT->value], true)) {
                return null;
            }

            return true;
        });
    }

    private function registerFortify(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(app(AuthenticateUser::class));
        // Fortify's confirm-password form counts wrong passwords with the in-app prompts.
        Fortify::confirmPasswordsUsing(function (User $user, ?string $password): bool {
            if (PasswordChecks::tooMany($user)) {
                throw ValidationException::withMessages(['password' => PasswordChecks::throttledMessage($user)]);
            }

            return PasswordChecks::passes($user, (string) $password);
        });
        Fortify::viewPrefix('auth.');

        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);

        // Route each user to the right area after login (see LoginResponse).
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        // A login that passed the two-factor challenge lands the same way.
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);

        // After registering / verifying, land the user in the app (Destination::home),
        // not on config('fortify.home') — the public landing. See the response classes.
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);

        // Middleware Fortify's own routes lack, added once every route is
        // registered (a cached route table carries it): impersonated sessions
        // stay off the two-factor routes, logging out while impersonating
        // only stops impersonating, a user whose role requires 2FA can't
        // disable it, and the password and sign-up endpoints get throttles.
        if (! $this->app->routesAreCached()) {
            $this->app->booted(function (): void {
                $throttles = [
                    'password.confirm.store' => 'throttle:password-confirm',
                    'password.email' => 'throttle:password-reset',
                    'password.update' => 'throttle:password-reset',
                    'register.store' => 'throttle:register',
                ];

                collect(Route::getRoutes()->getRoutes())->each(function (RouteDefinition $route) use ($throttles): void {
                    if (DenyLowAssuranceSessions::guardsFortifyRoute($route->getName())) {
                        $route->middleware(DenyLowAssuranceSessions::class);
                    }

                    if ($route->getName() === 'logout') {
                        $route->middleware(LeaveImpersonationOnLogout::class);
                    }

                    if ($route->getName() === 'two-factor.disable') {
                        $route->middleware(KeepMandatedTwoFactor::class);
                    }

                    if (isset($throttles[$route->getName()])) {
                        $route->middleware($throttles[$route->getName()]);
                    }
                });
            });
        }

        // Per email and address, plus a looser ceiling per address so one source
        // can't work through a list of emails. The address is only the visitor's
        // own behind a proxy once that proxy is trusted (config/trustedproxy.php).
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-confirm', fn (Request $request) => Limit::perMinute(5)->by('password-confirm:'.$request->user()->getAuthIdentifier()));

        // Like login: per email and address, with a ceiling per address.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('password-reset:'.Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(10)->by('password-reset-ip:'.$request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(5)->by('register:'.Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(10)->by('register-ip:'.$request->ip()),
        ]);

        // Log in with Google: per browser session, plus a ceiling per address loose
        // enough for an office behind one (each login is two requests).
        RateLimiter::for('google-login', fn (Request $request) => [
            Limit::perMinute(10)->by('google-login:'.$request->session()->getId()),
            Limit::perMinute(60)->by('google-login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
