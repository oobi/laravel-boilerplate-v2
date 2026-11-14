---
name: add-audit
description: 'Add audit logging for a model or an action. Use when asked to audit, log or record changes to a model, record that something happened (sent, approved, imported, exported), log a bulk or query-builder update, add an actor type (an import, an integration), or name and link a new kind of subject on the audit log screens.'
---

# Add audit logging

Full reference: [docs/boilerplate/audit-log.md](../../../docs/boilerplate/audit-log.md). Read it
before changing anything. This skill is the short procedure.

## Decide which kind

| What to record | Use |
|---|---|
| A model's creates, changes, deletes and restores | `use App\Models\Concerns\Auditable;` on the model |
| Something that happened that isn't one model save (sent, approved, imported, a relation sync) or a write that fires no model event (query-builder `update()`/`delete()`/`insert()`/`upsert()`, `saveQuietly()`, `updateQuietly()`, `Model::withoutEvents()`) | `App\Support\Audit\Audit::record($event, $subject, $properties, ...)` |
| Something that must carry on even if the log can't be written (rare: login style events) | `Audit::attempt(...)` |
| Work done by an import or integration, not a person | wrap it in `Audit::as('import', fn () => ..., actorName: '...')` |
| Values that changed, where your code knows which are secret (settings) | `Audit::recordChanges($event, ['field' => ['label' => ..., 'before' => ..., 'after' => ...]], ...)`: a Changes table, no name-based redaction, so pass a secret as `LogAuditEntry::REDACTED` |
| Bulk work that would flood the log | `Audit::withoutLogging(fn () => ...)`, then one `Audit::record()` summarising it |

## Procedure: a model

1. Add `use Auditable;`. Nothing else is required.
2. Noise, bulk or binary attributes: `protected array $auditExcept = ['...'];`.
3. Secrets: `$hidden`, `encrypted` and `hashed` casts, and the names in
   `config/audit.php` `redact` are redacted. A custom cast that decrypts must be
   in `$hidden`. JSON stored as a raw string in an uncast column is not inspected.
4. If the model defines its own `save()` or `delete()`, alias the trait's and
   call the alias, not `parent::` (see `App\Models\Role`):
   `use Auditable { Auditable::delete as auditedDelete; }`.
5. UUID or ULID primary keys do not fit the table (bigint morphs). Stop and raise
   it rather than working around it.
6. Name and link it on the screens: in the owning provider's `boot()`,
   `AuditDisplay::registerSubject(Model::class, fn (Model $m): array => ['label' => ..., 'url' => <only if not trashed and the viewer may open it, else null>])`.
   Soft-deleted subjects are still loaded, so guard with `! $m->trashed()`.
   Core registers its subjects in `AuditServiceProvider`, the teams tier in
   `TeamsServiceProvider`.
7. If the project uses a morph map, it must be set before the first entries are
   written; adding one later needs the stored `subject_type`/`causer_type`
   rewritten (see the doc's Morph maps section). Core sets no morph map.

## Procedure: an action

1. Event: dotted, past tense, `{subject}.{verb}` (`invoice.sent`,
   `payout.approved`). Reuse an existing event name for the same act.
2. Call `Audit::record()` inside the same transaction as the write, so the entry
   commits or rolls back with it. It throws on failure, on purpose.
3. Properties: the fields you chose, not a model (`['to' => $invoice->email]`,
   not `['invoice' => $invoice]`): a model stores far more than the entry needs.
   One that slips through has its own sensitive attributes redacted
   (`AuditProperties`, #84), but not inside an object of your own. Invalid UTF-8
   in anything a visitor typed is scrubbed.
4. Category: `AuditCategory::DATA` (default) for records, `ACCESS` for who can do
   what, `SECURITY` for account security, `AUTH` for logins, `SYSTEM` for the
   app itself. A project's own category is any string; name it with
   `AuditDisplay::registerCategory('billing', 'Billing', [AuditView::CHANGES])`
   in a provider's `boot()`. The third argument lists the list's quick views
   (`App\Support\Audit\AuditView`: CHANGES, SECURITY, EMAIL, SYSTEM) it shows
   under besides All; leave it out and it shows under All only.
5. Wording: the screens show the event as words ("Invoice sent"). For better
   wording and a detail-page sentence, call
   `AuditDisplay::registerEvent('invoice.sent', 'Invoice emailed', ':actor emailed an invoice to :subject')`
   in the project's provider `boot()`, in the same change that adds the event:
   the description is stored when the entry is written, so a wording added later
   doesn't reach existing entries in the list. Never edit core's
   `lang/en/audit.php` for a project's events or categories (it is upstream's
   and would conflict on every merge); that file is for core's own events only.
6. Backdating an imported history: `at: $originalTimestamp`. A causer other than
   the logged-in user: `by: $user`.

## A new actor type

`AuditDisplay::registerActor('import', 'Import', DaisyColor::ACCENT, 'heroicon-o-arrow-down-tray');`
in a provider's `boot()` (the icon is the actor's tile on the list; a cog if left
out), then `Audit::as('import', ...)` around the work.

## Tests

Feature test per entry point: assert the entry exists with its event, subject,
causer and the properties that matter, and that a failure to write it rolls the
change back where `record()` is used inside a transaction. See
`tests/Feature/Audit/CapturedActsTest.php` and
`tests/Feature/Teams/TeamAuditTest.php` (`breakTheAuditLog()`).

## Verify

Run the affected tests and `tests/Feature/Audit`. Open Admin > System > Audit log
and check the entry reads well: event in words, subject named and linked only for
someone who may open it.
