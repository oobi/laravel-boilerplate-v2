---
paths:
  - 'app/Actions/Impersonation/**,app/Http/Controllers/ImpersonationController.php,routes/web.php'
---

# Controllers

## Impersonation starts via a CSRF-protected action, never a GET route
Starting impersonation must never be a navigable GET (GitHub #11: a GET take route is CSRF-able via prefetch/link/redirect). Start it only from inside a CSRF-protected Livewire/Filament action by calling App\Actions\Impersonation\StartImpersonation::handle($target, $leaveRedirect?) — it re-authorizes at the write boundary via Gate::authorize(UserAbility::IMPERSONATE) (which wraps lab404's canImpersonate/canBeImpersonated guards) and records the leave origin. Filament triggers use ->action(fn () => app(StartImpersonation::class)->handle(...)) + ->successRedirectUrl(...), NOT ->url(). Never pass the post-take destination through a URL (no signed `next`); decide it server-side in the trigger. Leaving is the POST route users.impersonate.leave, driven by the banner's @csrf form — never GET. Don't reintroduce a take route or patch vendor lab404 files.

## Impersonation exits go through StopImpersonation; logout never logs out an impersonated user
Every way out of impersonation calls App\Actions\Impersonation\StopImpersonation::handle() (lab404's quiet guard swap, returns the leave destination): the banner's POST users.impersonate.leave and Fortify's `logout`, which AppServiceProvider::registerFortify() wraps in LeaveImpersonationOnLogout. A real logout while impersonating would end the impersonator's session and rotate the target's remember_token (signing them out on their own devices). Every layout an impersonated session can reach (app-shell, public, error, login) renders <x-impersonation-banner /> plus the is-impersonating body class. A new layout must too. Unverified users can't be impersonated (User::canBeImpersonated()). Triggers require confirmation. ShowUser keeps the action visible for an unverified target via canBeImpersonatedOnceVerified() and explains why in a modal with no submit button.
