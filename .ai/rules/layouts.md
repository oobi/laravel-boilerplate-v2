---
paths:
  - 'resources/views/layouts/**'
  - 'resources/views/components/app-shell.blade.php'
---

# Layouts

## Every layout renders both fixed top banners and their body classes
Every layout that renders its own <body> (app-shell, public, login, error) renders <x-environment-banner /> then <x-impersonation-banner />, and adds both body classes: has-environment-banner (App\Support\EnvironmentBanner::isShown()) and is-impersonating. The classes reserve the banners' summed height (--ui-top-banners-height in resources/css/theme/components/ui/layout.css) on the body padding and the admin shell's min-height and sticky top bar; a layout that skips one hides content under the strip. A new layout must do the same, and a utility like pt-* on the body overrides that padding, so drop it while a banner shows (see login.blade.php). Anything else fixed to the top (e.g. Filament toasts, .fi-no) offsets by --ui-top-banners-height.

## The document scrolls at every width, never <main>
The app shell has one scroll model: the page itself scrolls at every width. The top bar and the pinned sidebar are position: sticky, and the sidebar's nav scrolls on its own. Don't make <main> or a wrapper an overflow scroller (no h-dvh overflow-hidden shell): browsers only restore the document's scroll on Back and reload, so that broke Back from lg up (oobi #87). It also cost Safari's toolbar collapse below lg (#20), and gave every sticky element and scroll script two cases to handle. Scripts listen to window's scroll. See docs/boilerplate/design-system.md, "Page scrolling" (settled 2026-11-10).
