<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Enums\SystemPermission;
use App\Http\Responses\PasswordResetLinkResponse;
use Filament\Support\Facades\FilamentColor;
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
        $this->registerGates();
        $this->registerFortify();
        $this->registerFilamentIcons();
        $this->registerFilamentColors();
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
     * Mirrors tokens.css's light-theme daisyUI colors (single source of truth for the hex
     * itself — keep these two files in sync if a brand color changes there). Filament's own
     * built-in defaults for primary/danger/info/success/warning are generic Tailwind palettes
     * (Amber/Red/Blue/Green/Amber) with no relation to our brand colors, which made its
     * contrast-ratio shade selection (light vs dark background/text) pick shades that don't
     * match daisyUI's bolder look — registering our real colors fixes that. `secondary`/
     * `accent` have no Filament built-in at all. Either way, these hex values only drive shade
     * *selection*; the actual rendered color always comes from the CSS var override in
     * filament-colors.css, so it stays reactive to the light/dark theme toggle. `gray` is
     * registered for completeness, but Filament's button/badge/icon-button components treat
     * gray as "no color" and never emit a `.fi-color-gray` class for it (HasDefaultGrayColor)
     * — filament-buttons.css targets the resulting `:not(.fi-color)` state directly instead.
     */
    private function registerFilamentColors(): void
    {
        FilamentColor::register([
            'primary' => '#2563eb',
            'secondary' => '#293b67',
            'accent' => '#9333ea',
            'gray' => '#4a5565',
            'info' => '#0284c7',
            'success' => '#16a34a',
            'warning' => '#fcb700',
            'danger' => '#dc2626',
        ]);
    }

    /** One gate per SystemPermission, named after its string value (`@can('access admin panel')`). */
    private function registerGates(): void
    {
        foreach (SystemPermission::cases() as $permission) {
            Gate::define($permission->value, function ($user) use ($permission): bool {
                return method_exists($user, 'hasSystemPermission')
                    && $user->hasSystemPermission($permission);
            });
        }
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
