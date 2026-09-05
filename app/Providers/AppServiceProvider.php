<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\User;
use App\Observers\UserObserver;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesIconAlias;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

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
        $this->registerFilamentIcons();
        $this->registerNotifications();

        User::observe(UserObserver::class);
    }

    /** Top-center toasts instead of Filament's default top-right. */
    private function registerNotifications(): void
    {
        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::Start);
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
     * `grantSuperAdmin` are excluded only when the target is the actor
     * themselves, so those still fall through to UserPolicy's self-check.
     */
    private function registerAuthorization(): void
    {
        Gate::before(function (User $actor, string $ability, array $arguments = []): ?bool {
            if (in_array($ability, ['impersonate', 'assignRole'], true)) {
                return null;
            }

            if (! $actor->isSuperAdmin()) {
                return null;
            }

            $target = $arguments[0] ?? null;
            $isSelf = $target instanceof User && $target->id === $actor->id;

            if ($isSelf && in_array($ability, ['delete', 'toggleActive', 'grantSuperAdmin'], true)) {
                return null;
            }

            return true;
        });

        // The Roles admin screen (defining what a role can do at all) is
        // deliberately hardcoded super-admin-only, not permission-gated —
        // otherwise a role could grant itself broader permissions by editing
        // its own definition. Registered explicitly (rather than left
        // undefined) so nav visibility resolves deterministically.
        Gate::define('manage roles', fn (User $user): bool => $user->isSuperAdmin());
    }

    private function registerFortify(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(app(AuthenticateUser::class));
        Fortify::viewPrefix('auth.');

        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
