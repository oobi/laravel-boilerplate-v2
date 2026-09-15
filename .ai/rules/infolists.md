---
paths:
  - 'app/Livewire/**'
  - 'packages/*/src/Livewire/**'
---

# Infolists

## A linked infolist entry is coloured globally, not per entry
`TextEntry->url(...)` renders a bare `<a class="fi-in-entry-content">` that Filament gives layout but no colour, so a linked entry reads as plain black text with an invisible link — the infolist twin of the "entity names link to their view page" table rule. `resources/css/theme/components/ui/filament-infolist.css` colours every such anchor like `.ui-link`/`.link` (text-info, hover underline). Note it can't just colour the `<a>`: infolists paint the nested `.fi-in-text-item` `text-gray-950` and a trailing `->icon()` `text-gray-400` outright, and a descendant beats anything inherited — so the bridge reaches into both. So just add `->url()`: never hand-roll `link link-hover` onto an entry, and don't reach for `->color('info')` to fake it (that would also colour unlinked entries and is the escape hatch for an entry that genuinely wants a different colour — `.fi-color*` is excluded from the rule).
