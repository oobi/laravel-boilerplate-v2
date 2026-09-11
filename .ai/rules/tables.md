---
paths:
  - 'app/Livewire/**'
  - 'packages/*/src/Livewire/**'
  - 'resources/views/filament/tables/**'
  - 'packages/*/resources/views/filament/tables/**'
---

# Tables

## Row actions go in a menu, never as raw buttons
Every table's row actions are wrapped in `ActionGroup::make([...])` — the ⋮ menu (see `ListUsers`). Do not render row actions as inline buttons or links unless the user explicitly asks; the same goes for a hand-built daisyUI table, which must use a dropdown menu. A row of coloured buttons is noisy, wastes width, and is inconsistent with every other list.

## Entity names link to their view page, styled as links
The name/title cell of any listed entity is a link to its show page and reads as one (blue: `link link-hover`, as in `filament.tables.columns.user-column`). Use a ViewColumn (user-column, `teams::filament.tables.columns.team-column`) or an equivalent; never plain black text with an invisible `->url()`.

## A table's primary action lives in its toolbar
"Add …" / "Invite" for a table goes in the `<x-table-header>` `actions` slot, beside the search and filters — never in a row of its own above the table (a lone button row leaves a dead gap, see the team Members tab history) and, on a tabbed page, never in the shared page header. The one exception is a top-level list page whose page header *is* the table's header (Users, Teams), where "Add …" sits in the page header's actions. When the table is a shared child component, the action moves into the child and gates its own visibility.

## Users are listed with the user/avatar column
Wherever a table lists people — members, owners, authors, assignees — use `filament.tables.columns.user-column` (avatar, linked name, email, "You" badge). When the row isn't the user itself, pass the related `User` as the column's state (`ViewColumn::make('owner')`); the view accepts either. Never a bare name/email `TextColumn`.
