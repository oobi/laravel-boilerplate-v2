---
paths:
  - 'app/Http/Responses/**'
---

# Responses

## Post-login routing goes through LoginResponse + LoginRedirectRegistry
Where a user lands after login is owned by App\Http\Responses\LoginResponse (bound to Fortify's LoginResponse contract in AppServiceProvider::registerFortify()). Fixed cascade: system role -> route('dashboard'); else the first non-null resolver in App\Support\Auth\LoginRedirectRegistry wins; else the configurable tail. Add-ons contribute destinations via LoginRedirectRegistry::register(fn (User): ?string) from their own provider boot() — core never names them (the teams tier sends non-admins to route('team.index')). The tail for a user with no system role and no resolver is config('fortify.login_fallback'): 'home' (route('home')) or 'reject' (logout + back to login with auth.no_workspace). Don't hardcode a redirect target in auth code — register a resolver or set the fallback.
