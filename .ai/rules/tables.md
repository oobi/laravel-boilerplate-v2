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
Wherever a table lists people — members, owners, authors, assignees — use `filament.tables.columns.user-column` (avatar, linked name, email, "You" badge). When the row isn't the user itself, pass the related `User` as the column's state (`ViewColumn::make('owner')`); the view accepts either, and shows a related user compact (small avatar and name, no email) so it doesn't compete with the row's own subject. Never a bare name/email `TextColumn`.

## Tables stack their rows on a phone; never scroll sideways
Every Filament table is ->stackedOnMobile(), set globally in AppServiceProvider's Table::configureUsing(); filament-table.css tightens the cards below `sm` (first cell unlabelled, label and value on one line, row menu and checkbox top right). Hide low-value columns (dates such as last login, created, sent; counts) below a tablet with ->visibleFrom('lg'): they stay sortable and on the show page. ->visibleFrom() hides a column in the stacked phone card too, so never use it on a column the phone needs. A column of several badges adds ->wrap() so they stack rather than widen the table; a custom cell view (user-column, team-column) wraps from `sm` itself, since Filament's rows don't. A list of people whose other cells are self-describing badges (role, status) adds ->extraAttributes(['class' => 'ui-stacked-compact']) so the badges sit under the name like a native list item (ListUsers, MembersTable); not for a table with unlabelled dates or counts. Only a genuinely wide data grid opts out with ->stackedOnMobile(false) (theme-demo "wide"). Never use Split/Stack/Panel to make a table fit a phone: any layout component renders the table through Filament's layout renderer at every width and loses the desktop header cells. When a table does use layout components (row expansion), line its rows up with a Layout\Grid (fixed column spans), not a Split, which sizes each row's columns to their own content. Switch the Grid's columns by the table's width with a container breakpoint (`'@4xl' => 12`), not a screen one (`md`): beside the pinned sidebar a laptop's table is narrow. filament-table.css gives the rows of a table under 56rem the phone placement (checkbox and ⋮ top right). See theme-demo "maximalist".

## Tab panels holding a table go flush on their own
A `<x-tabs-nav>` panel whose content is just a table, or a toolbar (`<x-table-header>`) then a table, directly or inside one wrapper element (a Livewire component's root), drops its padding (tabs.css): the table loses its own border and the toolbar becomes a strip under the tabs. Don't pass `flush` for that, and don't add other elements beside the toolbar and table in such a panel, or it keeps the padding. The panel padding lives in tabs.css, not utility classes on the panel.
