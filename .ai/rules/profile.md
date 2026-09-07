---
paths:
  - app/Livewire/Profile/EditPassword.php
---

# Profile

## Password changes must invalidate the user's other sessions
AuthenticateSession is on the web group (bootstrap/app.php): it stamps the user's password hash into the session and logs out any session whose stamped hash no longer matches the stored one on its next request. This is what makes ALL password-change paths cut off other live sessions.

- Self-service (EditPassword) MUST call Auth::logoutOtherDevices($newPlaintext) right after the update, or AuthenticateSession logs out the acting device too. Requires the current-password proof (UpdateUserPassword's current_password: rule).
- Admin direct-set (EditUser, super-admin only via updatePasswordDirectly) and reset-link paths need nothing extra — changing the target's stored hash invalidates their sessions automatically; logoutOtherDevices can't be used (acts on the acting guard, not the target).

Driver-agnostic (hash lives in the session payload). Tests use the array driver which can't show a real two-request logout, so assert the OtherDeviceLogout event + that AuthenticateSession is on the web group. See docs/authentication.md.
