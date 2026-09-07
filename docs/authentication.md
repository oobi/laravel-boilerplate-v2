# Authentication

Auth is powered by [Laravel Fortify](https://laravel.com/docs/fortify) — the
frontend-agnostic backend that registers the login, registration, password
reset, email verification, and two-factor routes. The UI on top of it is built
from this app's own Livewire components, not Fortify's stock Blade views. The
custom action classes live in `app/Actions/Fortify/` and are wired up in
`AppServiceProvider::registerFortify()`.

This doc focuses on the two flows that are easy to get subtly wrong: **password
changes** (who can change whose password, and how other sessions are cut off)
and **account inactivation** (how access is revoked mid-session). See
`docs/permissions.md` and `.ai/rules/policies.md` for the RBAC model these
build on.

## Password changes

There are three distinct paths a password can change through. They differ in
**who** is acting and **what proves** the change is legitimate.

### 1. Self-service (the account owner) — `app/Livewire/Profile/EditPassword.php`

The owner changes their own password from the profile area. The proof of
legitimacy is the **current password**: `UpdateUserPassword` validates it with
Laravel's `current_password:` rule before writing the new hash. This is the
in-session equivalent of a reset link — it proves the person at the keyboard is
the owner, not someone who wandered up to an unlocked, already-logged-in
browser.

Because this proof is stronger than an emailed link (no phishable, interceptable
channel), self-service is **deliberately exempt** from the "send a reset link"
rule that applies to admins. Forcing a reset email here would be worse UX *and*
weaker security.

### 2. Admin sets a password directly — `EditUser::resetPasswordAction()`

Only a **super admin** may type a new password straight into another user's
account. This is gated by the `updatePasswordDirectly` policy ability, which
returns `false` for everyone in `UserPolicy` — the only way it passes is the
hardcoded super-admin `Gate::before()` bypass in `AppServiceProvider`. It is
never granted by a role.

The reason for the restriction: an admin setting *someone else's* secret is a
takeover primitive — they end up knowing a working credential for an account
that isn't theirs, with no proof the owner consented. Limiting it to a single
hardcoded super-admin flag keeps that power auditable and un-delegatable.

### 3. Admin sends a reset link — `EditUser::resetPasswordAction()`

Everyone else authorized to manage users gets this path instead of path 2. It
triggers `Password::sendResetLink()`, so the **owner** is the only one who ever
chooses the new secret. Gated by the `sendPasswordResetLink` ability, which
requires the `manage users` permission **and** forbids targeting a super admin
(`! $target->isSuperAdmin()`) — you cannot even email a reset link at a super
admin. Net effect: a super admin's password can only ever be changed by
themselves (path 1) or another super admin (path 2).

### Cutting off other sessions — `AuthenticateSession`

A password change is only half a security control if the attacker's *existing*
session survives it. All three paths above rely on Laravel's
`Illuminate\Session\Middleware\AuthenticateSession`, registered on the web
group in `bootstrap/app.php`.

How it works: on each request the middleware stamps the user's current password
**hash** into their session, and compares the stamped value against the stored
hash. When the hash changes, every *other* session still holds the old value,
so each one fails the comparison and is logged out on its next request. This is
storage-driver-agnostic — the hash lives inside the session payload, so it
behaves identically whether sessions are stored in the database (the default
here), Redis, files, or anything else. The comparison is application-level, not
storage-level.

Two consequences worth understanding:

- **Self-service keeps the current device alive.** Left alone, the change would
  log out the acting device too (its stamped hash is now stale). So
  `EditPassword` calls `Auth::logoutOtherDevices($newPassword)` right after the
  update — it re-stamps the current session with the fresh hash while dropping
  all the others. This needs the new plaintext, which self-service has.
- **Admin resets can't call `logoutOtherDevices`.** That method acts on the
  *acting* guard's user, and the admin is not the target. Nothing extra is
  needed: changing the target's stored hash is enough for `AuthenticateSession`
  to invalidate the target's other sessions on their next request.

> **Testing note:** the test suite uses the non-persistent `array` session
> driver, which can't demonstrate a real two-request logout. The behaviour is
> covered instead by asserting the `OtherDeviceLogout` event fires on a
> self-service change and that `AuthenticateSession` is present on the web group
> (see `tests/Feature/Profile/EditPasswordTest.php`).

## Account inactivation

Every user row has a boolean `active` column (default `true`). Deactivating a
user must revoke access **immediately**, even for someone who is already logged
in — not just block their next login.

That mid-session enforcement is `app/Http/Middleware/EnsureAccountIsActive.php`,
also on the web group. On every web request (including Livewire updates) it
checks the authenticated user's `active` flag; if the account has been
deactivated since they signed in, it logs them out, invalidates the session, and
redirects to login with the `auth.inactive` message. It also covers the
in-between state of a **pending 2FA challenge** (a user who passed the password
step but hasn't entered their code yet) and, during **impersonation**, requires
both the impersonated user and the original impersonator to remain active.

The check can be globally disabled with `AUTH_BLOCK_INACTIVE_USERS=false`
(config key `auth.block_inactive_users`) — useful for local debugging, never in
production.

**Toggling active status** is a per-instance policy ability, `toggleActive` in
`UserPolicy`: it requires the `suspend users` permission, forbids toggling a
super admin, and forbids toggling **yourself** (including a super admin — nobody
can lock themselves out). It's surfaced on the admin Users list and edit
screens (`app/Livewire/Admin/Users/*`) and the
`UserInformationFormSection` panel.
