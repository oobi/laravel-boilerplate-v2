<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Turns a "page expired" (419) on a guest auth form into a trip back to that form.
 *
 * A sign-in page left open past the session lifetime posts a CSRF token whose
 * session is gone. Rather than the 419 error page, the user lands on the same
 * form with what they typed (never a password or code) and a notice to try again.
 * Only the forms listed here recover; anything else keeps the 419 page. Targets
 * come from this list, never from the Referer header, which the browser controls.
 *
 * Add-ons register their own guest forms from their provider (the teams tier's
 * invitation sign-up). See docs/boilerplate/authentication.md.
 */
class ExpiredForms
{
    /** The session flash key the login layout reads to show its notice. */
    public const string FLASH_KEY = 'page_expired';

    /** Input never flashed back to the form. */
    private const array SECRET_INPUT = [
        '_token',
        'password',
        'password_confirmation',
        'current_password',
        'code',
        'recovery_code',
        'token',
    ];

    /** @var array<string, Closure(Request): ?string> */
    private static array $registered = [];

    /**
     * @param  string  $routeName  The form's POST route.
     * @param  (Closure(Request): ?string)|null  $returnTo  The URL to send the user back to, or null for the 419 page.
     *                                                      Defaults to the posted URL itself, as a GET.
     */
    public static function register(string $routeName, ?Closure $returnTo = null): void
    {
        self::$registered[$routeName] = $returnTo ?? self::sameUrl(...);
    }

    /** A redirect back to the form for a stale-token 419 on a listed form, or null to render as usual. */
    public static function recover(HttpException $exception, Request $request): ?RedirectResponse
    {
        if ($exception->getStatusCode() !== 419
            || ! $exception->getPrevious() instanceof TokenMismatchException
            || $request->expectsJson()) {
            return null;
        }

        $returnTo = self::forms()[$request->route()?->getName() ?? ''] ?? null;
        $url = $returnTo === null ? null : $returnTo($request);

        if ($url === null) {
            return null;
        }

        return redirect()->to($url)
            ->withInput($request->except(self::SECRET_INPUT))
            ->with(self::FLASH_KEY, true);
    }

    /** Drop add-on registrations, for tests. Core's forms are always listed. */
    public static function flush(): void
    {
        self::$registered = [];
    }

    /** @return array<string, Closure(Request): ?string> */
    private static function forms(): array
    {
        return [
            'login.store' => self::sameUrl(...),
            'register.store' => self::sameUrl(...),
            'password.email' => self::sameUrl(...),
            'password.confirm.store' => self::sameUrl(...),
            'password.update' => self::passwordReset(...),
            // The challenge lives in the session, so it expired with it: start again from login.
            'two-factor.login.store' => fn (): string => route('login'),
            ...self::$registered,
        ];
    }

    private static function sameUrl(Request $request): string
    {
        return $request->fullUrl();
    }

    /** The reset form is reached by its emailed link, so rebuild that from the posted token. */
    private static function passwordReset(Request $request): ?string
    {
        $token = $request->input('token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        $email = $request->input('email');

        return route('password.reset', array_filter([
            'token' => $token,
            'email' => is_string($email) ? $email : null,
        ]));
    }
}
