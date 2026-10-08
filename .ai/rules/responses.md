---
paths:
  - 'app/Http/Responses/**'
---

# Responses

## Post-login routing goes through LoginResponse + LoginRedirectRegistry
Where a user lands after login is owned by App\Http\Responses\LoginResponse (bound to Fortify's LoginResponse contract in AppServiceProvider::registerFortify()). Fixed cascade: system role -> route('dashboard'); else the first non-null resolver in App\Support\Auth\LoginRedirectRegistry wins; else the configurable tail. Add-ons contribute destinations via LoginRedirectRegistry::register(fn (User): ?string) from their own provider boot(): core never names them (the teams tier's TeamDestination sends non-admins to their team, the team picker or onboarding). The tail for a user with no system role and no resolver is config('fortify.login_fallback'): 'home' (route('home')) or 'reject' (refused in AuthenticateUser with auth.no_workspace before login; LoginResponse logs out as a backstop). Don't hardcode a redirect target in auth code. Register a resolver or set the fallback.

## Two-factor logins, early reject and the guest redirect share the cascade
LoginResponse is bound to both Fortify's LoginResponse and TwoFactorLoginResponse contracts, so a login through the 2FA challenge lands like a password login. Under LOGIN_FALLBACK=reject, AuthenticateUser refuses a user Destination::claimed() can't place (auth.no_workspace) after the password checks, before they're logged in or sent to the challenge; LoginResponse's reject is only the backstop. A signed-in user opening a guest page is sent to Destination::home() via $middleware->redirectUsersTo() in bootstrap/app.php, never route('dashboard') (403 for non-admins). Any new landing logic goes through Destination, not a hardcoded route.
