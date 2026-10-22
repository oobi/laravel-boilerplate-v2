---
paths:
  - app/Http/Controllers/LoginOnlyHomeController.php
---

# Http Controllers

## The login-only home is a sanctioned GET exit from impersonation
Leaving impersonation is POST-only everywhere else (controllers.md), but LoginOnlyHomeController (the `home` route of a login-only app, bp:make-login-only) ends an impersonation on GET `/` when nothing claims the target, via StopImpersonation, with a notice. Deliberate: that session has no page it can reach, and the only effect of a forged request is returning the admin to their own session. Likewise it signs out (NoWorkspaceResponse) a user nothing claims on GET. Don't add other GET exits on the strength of this one.
