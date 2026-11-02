---
paths:
  - 'resources/views/layouts/**'
  - 'resources/views/components/app-shell.blade.php'
---

# Layouts

## Every layout renders both fixed top banners and their body classes
Every layout that renders its own <body> (app-shell, public, login, error) renders <x-environment-banner /> then <x-impersonation-banner />, and adds both body classes: has-environment-banner (App\Support\EnvironmentBanner::isShown()) and is-impersonating. The classes reserve the banners' summed height (--ui-top-banners-height in resources/css/theme/components/ui/layout.css) on the body padding and the h-dvh admin shell; a layout that skips one hides content under the strip. A new layout must do the same, and a utility like pt-* on the body overrides that padding, so drop it while a banner shows (see login.blade.php). Anything else fixed to the top (e.g. Filament toasts, .fi-no) offsets by --ui-top-banners-height.
