---
paths:
  - 'app/**'
---

# App

## Prefer collect() chains over array_map/foreach
Use fluent `collect()->map()->filter()...` chains for collection transformations instead of `array_map`/`array_filter` or manual `foreach`.

## Email addresses are normalized at the User model, and uniqueness is validated against the normalized value
`User::email` has an `Attribute::set` mutator that trims and lowercases, so every write path (forms, Fortify actions, `bp:make-admin`, seeders, factories) stores a canonical address. This is not cosmetic: `fortify.lowercase_usernames` is on, so a mixed-case stored email can never be logged in with on a case-sensitive connection.

Any new form or action that accepts an email must therefore validate uniqueness against `User::normalizeEmail($value)`, not the raw input — otherwise a case-variant of a taken address passes validation and then hits the `users.email` unique index as a QueryException. In Filament schemas do this with `->mutateStateForValidationUsing(fn (?string $state): ?string => User::normalizeEmail($state))` before `->unique(...)`; in plain `Validator::make()` paths normalize `$input['email']` first.

`users` uses soft deletes, and Laravel's `unique` rule is not soft-delete aware — that is correct here, since a trashed user still holds the row and the DB index. Don't add `withoutTrashed()` to these rules.
