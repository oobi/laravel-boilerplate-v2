# Panels

How the Users Show/Edit admin pages are composed, and how to add to them
without editing core files. See `~BOILERPLATE_v2.md` for why this exists
(Teams must be able to add to these pages as an opt-in, additive layer).

## The idea

A "panel" is a small class that contributes either:

- **a card** to a Show page (`ShowPanel`) — freeform markup (a Blade view, a
  component, whatever), or
- **fields** to an Edit page's single form (`FormSection`) — must return
  Filament schema components, since an Edit page is one Filament form.

A class can implement one or both. Panels are plain classes under
`app/Panels/{Resource}/*` (e.g. `app/Panels/Users/*`) — nothing registers
itself; you always add an entry somewhere explicit (see below).

## Where panels are registered — how to find out what's active

One mechanism: `PanelRegistry::for(string $key)->add(string $panelClass, ...)`
— fetch-or-create the ordered panel list for a page key (e.g. `users.show`),
then append one or more classes.

1. **`App\Support\Panels\AdminPanels::define()`** — the app's own curated
   defaults, grouped by resource + page:
   ```php
   class AdminPanels
   {
       public static function define(): void
       {
           PanelRegistry::for('users.show')->add(
               StatisticsPanel::class,
               SecurityPanel::class,
           );

           PanelRegistry::for('users.edit')->add(
               UserInformationFormSection::class,
           );
       }
   }
   ```
   This is the first place to look — it's a plain method, so you can see
   exactly what ships by default, comment out a line to disable a panel, or
   reorder lines to reorder cards. `AdminPanels::define()` runs once per
   request from `PanelsServiceProvider::boot()`.

2. **The same `PanelRegistry::for($key)->add(...)` call**, made from an
   add-on's **own** service provider, is how something like a future Teams
   tier contributes a panel without editing `AdminPanels` or the host
   Livewire component. To find every registration, grep the codebase for
   `PanelRegistry::for(`.

   Example (see `app/Providers/PanelExtensionDemoServiceProvider.php` for a
   real, working one):
   ```php
   class TeamsServiceProvider extends ServiceProvider
   {
       public function boot(): void
       {
           PanelRegistry::for('users.show')->add(TeamMembershipsPanel::class);
       }
   }
   ```
   Registered in `bootstrap/providers.php` like any other provider.

So: **`grep -rn "PanelRegistry::for("`** always tells you the complete,
current picture.


## Metadata every panel gets for free

Panels use the `HasPanelMetadata` trait, which supplies sensible defaults so
you only override what differs:

| Method | Default | Purpose |
|---|---|---|
| `key()` | kebab-case class name | Used to look a panel up (e.g. for `callPanelAction`) |
| `order()` | `0` | Sort order within its region |
| `region()` | `PanelRegion::Main` | `Main` (wide column) or `Sidebar` (narrow column) on Show pages |
| `visible($subject, $viewer)` | `true` | Return `false` to hide conditionally (permissions, feature flags, etc.) |

## Practical example: a read-only Show panel

```php
namespace App\Panels\Users;

use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\PanelRegion;
use App\Support\Panels\Contracts\ShowPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class ArticlesAuthoredPanel implements ShowPanel
{
    use HasPanelMetadata;

    public function order(): int
    {
        return 15; // renders after StatisticsPanel (10), before SecurityPanel (20)
    }

    public function region(): PanelRegion
    {
        return PanelRegion::Sidebar;
    }

    public function render(Model $subject): View
    {
        return view('panels.users.articles-authored', ['user' => $subject]);
    }
}
```

Then register it — core panel: add the class to `AdminPanels::define()`
under `PanelRegistry::for('users.show')`. Add-on panel: call
`PanelRegistry::for('users.show')->add(ArticlesAuthoredPanel::class)`
from your own provider's `boot()`.

Generate the boilerplate for this with `php artisan bp:make:panel` — see
`docs/commands.md`.

## Panel-triggered actions (e.g. "force disable 2FA")

A `ShowPanel` can also implement `HasPanelActions` to expose named mutations
that its own view triggers via a plain Livewire call — see
`App\Panels\Users\SecurityPanel` for a real example (2FA status + a
confirmed "force disable" button). The host page (`ShowUser`) exposes one
generic `callPanelAction(string $panelKey, string $action)` method that
resolves the panel by `key()` and invokes the matching closure — panels never
need to know anything about the host component.

```blade
<button
    wire:click="callPanelAction('security', 'force-disable-2fa')"
    wire:confirm="Are you sure?"
>
    Force Disable 2FA
</button>
```

## Practical example: an Edit form section

```php
namespace App\Panels\Users;

use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\FormSection;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

class NotificationPreferencesFormSection implements FormSection
{
    use HasPanelMetadata;

    /** @return array<int, \Filament\Schemas\Components\Component> */
    public function components(Model $subject): array
    {
        return [
            Section::make('Notification Preferences')
                ->schema([
                    Forms\Components\Toggle::make('notify_by_email'),
                ]),
        ];
    }
}
```
Register the same way, via `AdminPanels::define()` under
`PanelRegistry::for('users.edit')->add(...)`, or from an add-on's own
provider. `EditUser::save()` persists
`$this->form->getState()` as-is, so a new *ordinary* field just needs a
matching column on `User` — no changes needed in `EditUser.php` itself.
(Filament excludes `disabled()` fields from that state automatically, which
is how the existing "can't change your own active status" rule works
without any special-casing in `EditUser`.) A **sensitive** field (one with
its own distinct ability, e.g. role/super-admin assignment) should not go
through this generic path at all — see `EditUser::manageRolesAction()`/
`toggleSuperAdminAction()` for the pattern: its own Filament Action,
independently authorized both for visibility and again inside the action
closure at the write boundary.

## What's deliberately NOT a panel

The "User Information" card on the Show page stays a method
(`ShowUser::userInfolist()`) directly on the host component rather than a
registered `ShowPanel`. Filament's `InteractsWithSchemas` requires that
method to live on the `HasSchemas` component itself — it can't be resolved
from an arbitrary class the way freeform `ShowPanel`s can. In practice this
doesn't limit extensibility: new panels never need to touch it.

## Ejecting / hand-tooling a layout

Nothing here is hidden in a package. If a page ever needs a one-off layout
that doesn't fit the panel model, `App\Support\Panels\AdminPanels` and the
`resources/views/livewire/admin/*.blade.php` region loops are plain app
files — edit them directly.
