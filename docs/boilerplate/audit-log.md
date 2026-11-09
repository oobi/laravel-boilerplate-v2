# Audit log

Who did what, and when. Built into core on
[spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog), with
our own model, capture, screens and export on top.

## What it records

Without any work from you:

- **Login:** logged in, logged out, failed login, locked out, registered,
  email verified.
- **Account security:** password changed, reset or set by an admin, reset link
  sent, two factor turned on, off or used with a recovery code, Google account
  linked or unlinked, Google email changed.
- **Access:** impersonation started and ended, roles changed, role permissions
  changed, super admin granted or revoked, user deactivated or reactivated, two
  factor turned off or its grace period reset by an admin, team membership and
  ownership (teams tier), audit log exported.
- **Records:** every create, change, delete and restore on an `Auditable`
  model, with before and after values. Core opts in `User` and `Role`; the
  teams tier opts in `Team`, `TeamInvitation` and `Domain`.
- **System:** scheduled tasks that finish, fail or are skipped, and queued jobs
  that fail.

Each entry stores the actor (a person, a visitor, a queued job, the scheduler or
the system), their name at the time, who was impersonating them if anyone, the
IP address and browser for web requests, the category, the event and the
subject. It also stores the browser session it came from, so one session can be
read back in order (see [Sessions](#sessions)).

## Reading it

**Admin > System > Audit log** (`audit.index`): newest first, with search and
filters for category, event (searchable), actor type and date. Each entry has a
detail page (`audit.show`): the event in words with a sentence saying what
happened ("Super Admin updated Michel Adams"), each changed field before and
after with the changed part of a short value marked, any other properties, and
the request's browser behind a disclosure. Its **Session timeline** button (also in
each row's menu on the list) lists everything from the same browser session,
oldest first. A user's page has an
**Activity** panel with their latest five entries and a link to the full list,
filtered to them.

### Sessions

Every entry from a web request with a session carries a session trail: a random
ULID in `session_trail`, started the first time a browser session needs one (its
first entry, or a job it queues) and kept in the session until it ends. So one
trail reads as one sitting, in order: failed
logins, the login, each change, the logout. After a breach, the actions taken
with a stolen session share the victim's trail but not their IP address or
browser.

- **Not the session ID.** Laravel's session ID would let anyone reading the log
  or a backup take over a live session, and it changes at login. The trail is
  minted by `App\Support\Audit\SessionTrail` and means nothing outside the log.
- **When one starts and ends.** It survives the login (Laravel keeps session data
  when it renews the ID), so failed attempts and the login that follows share
  it. Logout and an expired session end it; a remember-me login after that
  starts a new one. Impersonation stays in the admin's trail, and its entries
  name the impersonator.
- **Queued jobs** dispatched in a session carry its trail, as they carry the
  person who dispatched it. Artisan commands and the scheduler have none.
- **No trail:** a route without a session (an API route), and work done after
  the response is sent (`defer()`, `dispatchAfterResponse()`, a terminating
  callback). By then Laravel has saved the session, so a trail minted there
  could never be stored.
- **Opening one:** **Session timeline** on an entry's page or in its row's menu,
  or `?session=<trail>` on the list. The list then reads oldest first, and
  **Export CSV** follows it. The export has a `session` column either way.
- **Only audited events**, not page views: the trail shows what changed and who
  logged in, not each page visited between.
- A seeder or import can lend its entries a trail with
  `Context::scope($callback, hidden: [SessionTrail::CONTEXT => (string) Str::ulid()])`,
  as the demo seeder does.

Two permissions, assignable to any role on the Roles screen:

| Permission | Opens |
|---|---|
| `view audit log` | The list, detail pages and the Activity panel. Shows names and email addresses, even to someone without `view users`. |
| `export audit log` | The **Export CSV** button: a file holding what the filters show, in the screen's default order (newest first; one session oldest first). Each export is itself recorded. |

`view audit log` implies `access admin panel`; `export audit log` implies
`view audit log`. A super admin has both, and so does the seeded Administrator
role on a fresh install. **On an existing install** the roles seeder doesn't run
again, so no role has them until you grant them on the Roles screen.

## Logging a model's changes

```php
use App\Models\Concerns\Auditable;

class Invoice extends Model
{
    use Auditable;

    /** Left out: noise, bulk, or can't be stored as text. */
    protected array $auditExcept = ['last_viewed_at'];
}
```

That is all a model needs. Things to know:

- **A write and its entry commit together.** `save()`, `delete()` and
  increments run in a transaction, so if the entry can't be written the write
  fails too. After a rollback the model in memory is stale: retry with a fresh
  instance.
- **A model with its own `save()` or `delete()`** replaces the trait's. Alias
  the trait's and call the alias, not `parent::`:
  `use Auditable { Auditable::delete as auditedDelete; }`. `Role` does this.
- **Some writes fire no model events**, so they aren't seen: query builder
  `update()`, `delete()`, `insert()` and `upsert()`, `saveQuietly()`,
  `updateQuietly()` and anything inside `Model::withoutEvents()`. Record them
  with `Audit::record()`.
- **A save that only touches `$auditExcept` attributes writes no entry.**
- **Sensitive values are redacted**: `$hidden` attributes, encrypted and
  hashed casts, and any name matching `config/audit.php` `redact` (passwords,
  tokens, secrets, keys and so on). A custom cast that decrypts a value can't be
  detected: put that attribute in `$hidden`. JSON held as a raw string in an
  uncast column isn't looked inside.
- **Binary attributes** can't be stored as text: put them in `$auditExcept`.
- **Long strings** are cut to `max_value_length` (2,000 characters).
- **UUID and ULID keys don't fit**: the table's subject and causer ids are
  big integers.

## Logging anything else

```php
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;

Audit::record('invoice.sent', $invoice, ['to' => $invoice->email]);

Audit::record(
    'payout.approved',
    $payout,
    properties: ['amount' => $payout->amount],
    description: 'Payout approved for '.$payout->reference,
    category: AuditCategory::ACCESS,
);
```

- **The event** is dotted and past tense. It reads as words on the screens:
  `invoice.sent` becomes "Invoice sent". To word it yourself, and give the
  detail page a sentence, register it from a provider's `boot()` (never edit
  core's `lang/en/audit.php`, which upstream changes):

  ```php
  AuditDisplay::registerEvent('invoice.sent', 'Invoice emailed', ':actor emailed an invoice to :subject');
  ```

  Register it before the event ships: an entry's description is stored when it
  is written, so a wording added later doesn't reach older entries in the list.
  Their detail page picks up the new heading and sentence. Renaming a wording
  you had already registered is different: older entries keep the old wording,
  and their detail page shows it under the new heading as if someone had written
  it. A description you pass to `record()` always shows under the heading, ahead
  of any sentence. Without a sentence, a model's created, updated and deleted
  events still read as one ("Jo updated Invoice 12"); anything else shows
  nothing there.
- **The category** is one of `AuditCategory`'s cases or your own string.
  Records (`data`) is the default. Name your own:
  `AuditDisplay::registerCategory('billing', 'Billing');`.
- **Field and property names** read in words for core's fields ("Email
  address"); anything else is tidied (`due_date` reads "Due date").
- **Properties** hold anything else worth keeping. Pass the fields you chose,
  never a whole model: a model's encrypted attributes would be stored as plain
  text. Redaction applies here too.
- **`by:`** sets the causer when it isn't the logged-in user. **`at:`** backdates
  an entry, for an import.
- **`record()` throws** if the entry can't be written, so in a transaction it
  commits or rolls back with the change. For something that must carry on
  regardless (the way logins and scheduled tasks are logged), use
  `Audit::attempt()`: it reports the failure instead.
- **A password set directly** (not through Fortify or the admin screens)
  isn't seen: `password` is left out of `User`'s audit, so record it.

### Who did it

Filled in for you:

- **A web request:** the logged-in person, or a visitor if no one is logged in.
- **A queued job:** shown as a queued job, with whoever dispatched it carried
  into the job as the person. A job nobody dispatched is a queued job alone.
- **Anything else** (an Artisan command, a scheduled command's own changes): the
  system. The scheduler is the actor only on the finished, failed and skipped
  entries for its tasks.

For work done by an import or an integration, say so:

```php
use App\Support\Audit\Audit;

Audit::as('import', fn () => $importer->run(), actorName: 'Nightly CRM import');
```

This sets the actor type even when someone is logged in. They stay the causer,
and their name stays on the entry unless you pass `actorName`. The actor reaches
any job dispatched inside the block. Give a new actor type a label and colour
from a provider's `boot()`:

```php
use App\Support\Audit\AuditDisplay;
use App\Support\Theme\DaisyColor;

AuditDisplay::registerActor('import', 'Import', DaisyColor::ACCENT);
```

To write nothing at all, for bulk work that would flood the log:
`Audit::withoutLogging(fn () => ...)`.

### Naming and linking subjects

Unless told otherwise, the screens name a subject by its class and id ("Invoice
12"), with no link. Register how to name and link a model of your own, from a
provider's `boot()`. Only return a URL the viewer may open. A soft-deleted
subject is still loaded, so don't link one whose page would 404:

```php
use App\Support\Audit\AuditDisplay;
use Illuminate\Support\Facades\Gate;

// Assumes an InvoicePolicy with a view() method.
AuditDisplay::registerSubject(Invoice::class, fn (Invoice $invoice): array => [
    'label' => $invoice->reference,
    'url' => ! $invoice->trashed() && Gate::allows('view', $invoice) ? route('invoices.show', $invoice) : null,
]);
```

An entry outlives the code it describes. If a subject's class is later removed
(as `bp:remove-teams` removes the teams models), the entry still shows, as
"Team 7".

### Morph maps

An entry stores the subject's and causer's morph type as it was when written:
the class name, or its alias if a morph map was set. Set a morph map before the
first entries are written. Adding one later splits the history: a person's
older entries, stored under `App\Models\User`, drop out of their Activity panel
and the list filtered to them. If you add one, rewrite the stored
`subject_type` and `causer_type` values in the same migration. An alias whose
class has gone reads as the alias in words ("Import Run 5" from `import_run`).

## Settings

| Setting | Default | Does |
|---|---|---|
| `AUDIT_ENABLED` | `true` | `false` writes nothing and removes the screens, the nav item, the panel and the daily clean. Existing entries stay, and the two permissions stay on the Roles screen. Re-cache config and routes after changing it. |
| `AUDIT_RETENTION_DAYS` | `365` | Entries older than this are deleted daily (`activitylog:clean`, scheduled). `0` keeps them forever. A deleted person's name stays on their entries until then. |
| `AUDIT_LOG_SCHEDULE_ROUTINE` | `true` | Scheduled tasks that finish or are skipped. Failures are always logged. Turn it off if an every-minute task makes it noisy. |

`config/audit.php` also holds the `redact` list and `max_value_length`.

## Good to know

- **The model refuses to change or delete an entry.** Retention deletes them
  with a query, which the model doesn't see, and so would any other query.
- **During an audit outage** (the table can't be written), anything recorded
  with `Audit::record()` or through `Auditable` is refused along with its
  change: a password change, roles, impersonation starting, a profile edit, an
  export. These carry on, and the failure is reported: logging in and out, a
  failed login or lockout, email verification, a password reset by email link
  (unless the account has Google linked: the reset unlinks it, which is
  recorded, so the reset is refused and nothing changes), two factor events, a
  Google email change at login, leaving impersonation, scheduled tasks and
  failed jobs. A first Google login (it links the account) and a registration
  (it creates a user) are refused.
- **An `Auditable` model's observers and listeners run inside its save
  transaction.** Queue connections have `after_commit` off, so a job they
  dispatch on Redis or SQS can start before the commit.
- **The export** has no PHP time limit. A very long one needs the web server's
  timeout to match. The file starts with a byte order mark so Excel reads its
  encoding. A cell that could run as a spreadsheet formula gets an apostrophe in
  front.
- **The filter dropdowns** list what is in the log, cached for five minutes, so
  a new kind of entry can take that long to appear in them.
- **A scheduled task's description** is stored as written in
  `routes/console.php`: don't put secrets in it.

## Testing

The audit tests are in `tests/Feature/Audit`. Logging is on in tests, so an
`Auditable` model's factories write entries. Assert on them through
`AuditEntry`:

```php
$this->assertTrue(AuditEntry::query()->where('event', 'invoice.sent')->exists());
```
