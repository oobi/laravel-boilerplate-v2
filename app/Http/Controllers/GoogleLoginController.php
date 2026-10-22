<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GoogleLogin\AuthenticateWithGoogle;
use App\Actions\GoogleLogin\LinkGoogleAccount;
use App\Models\User;
use App\Support\Auth\Destination;
use App\Support\Auth\GoogleLogin;
use App\Support\Auth\SessionAssurance;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\AbstractUser as GoogleUser;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Log in with Google, and the return leg of linking a Google account from the
 * profile. Both come back to one callback; a link, started behind the password
 * prompt on the profile's Authentication tab, is marked in the session
 * (GoogleLogin::LINKING_KEY). Every route 404s while the feature is off
 * (GoogleLogin::enabled()). See docs/authentication.md.
 */
class GoogleLoginController extends Controller
{
    public function redirect(): Response
    {
        abort_unless(GoogleLogin::enabled(), 404);

        return GoogleLogin::driver()->redirect();
    }

    public function callback(Request $request, AuthenticateWithGoogle $authenticateWithGoogle, LinkGoogleAccount $linkGoogleAccount): Response
    {
        abort_unless(GoogleLogin::enabled(), 404);

        $linkingUserId = $request->session()->pull(GoogleLogin::LINKING_KEY);

        if ($linkingUserId !== null) {
            return $this->finishLinking($request, $linkingUserId, $linkGoogleAccount);
        }

        if ($user = $request->user()) {
            return redirect()->to(Destination::home($user));
        }

        try {
            $user = $authenticateWithGoogle->handle($this->googleUser($request));
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors());
        }

        return $this->logIn($request, $user);
    }

    /** Linking must finish in the session that started it, by the account holder. */
    private function finishLinking(Request $request, mixed $linkingUserId, LinkGoogleAccount $linkGoogleAccount): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->getKey() === $linkingUserId && ! SessionAssurance::isLow(), 403);

        try {
            $linkGoogleAccount->handle($user, $this->googleUser($request));
        } catch (ValidationException $exception) {
            Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();

            return redirect()->route('profile.two-factor');
        }

        Notification::make()->title(__('auth.google_linked'))->success()->send();

        return redirect()->route('profile.two-factor');
    }

    /**
     * The Google account that came back, or a refusal under `email` when the
     * visitor cancelled, the state didn't match, or Google couldn't be reached.
     */
    private function googleUser(Request $request): GoogleUser
    {
        if ($request->filled('error')) {
            GoogleLogin::refuse('auth.google_cancelled');
        }

        try {
            return GoogleLogin::driver()->user();
        } catch (InvalidStateException) {
            GoogleLogin::refuse('auth.google_expired');
        } catch (Throwable $exception) {
            report($exception);

            GoogleLogin::refuse('auth.google_failed');
        }
    }

    /**
     * Signs the user in as Fortify's own login would: through the two-factor
     * challenge when they have it on, otherwise straight to LoginResponse.
     * Never remembered: signing in with Google again is one click.
     */
    private function logIn(Request $request, User $user): Response
    {
        if (Fortify::confirmsTwoFactorAuthentication()
            ? $user->two_factor_secret && $user->two_factor_confirmed_at
            : $user->two_factor_secret) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::guard(config('fortify.guard'))->login($user);
        $request->session()->regenerate();

        return app(LoginResponse::class)->toResponse($request);
    }
}
