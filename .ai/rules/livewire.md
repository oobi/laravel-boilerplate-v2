---
paths:
  - 'app/Livewire/**'
---

# Livewire

## No repository/query-object layer
Query Eloquent models directly in Livewire components (e.g. `User::query()`) — no repository or dedicated query-object layer.

## Livewire components are class-based MFC, full-page only
Livewire components are multi-file (class + separate `resources/views/livewire/**/*.blade.php` view), routed as full-page components. Don't use single-file components (SFC) or Volt.

## Validation lives in Filament Schema field rules, not Form Requests
Forms are Filament Schemas (`implements HasSchemas`, `use InteractsWithSchemas`, `form(Schema $schema)`), validated declaratively via field-level rules (`->required()`, `->maxLength()`, `->unique()`, etc.) — not Form Request classes or Livewire `rules()`/`#[Validate]`. `app/Http/Requests` and `app/Policies` don't exist and aren't the pattern here (see providers.md).
