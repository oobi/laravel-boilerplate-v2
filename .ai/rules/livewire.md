---
paths:
  - 'app/Livewire/**'
---

# Livewire

## No repository/query-object layer
Query Eloquent models directly in Livewire components (e.g. `User::query()`) — no repository or dedicated query-object layer.

## Livewire components are a class plus a separate view, full-page only
Livewire components are a class in `app/Livewire/**` plus a separate `resources/views/livewire/**/*.blade.php` view (Livewire 4's `class` type, which `php artisan make:livewire` produces: `make_command.type` in config/livewire.php), routed as full-page components. Don't use single-file (SFC) or folder (Livewire 4 MFC) components, or Volt.

## Validation lives in Filament Schema field rules, not Form Requests
Forms are Filament Schemas (`implements HasSchemas`, `use InteractsWithSchemas`, `form(Schema $schema)`), validated declaratively via field-level rules (`->required()`, `->maxLength()`, `->unique()`, etc.) — not Form Request classes or Livewire `rules()`/`#[Validate]`. `app/Http/Requests` doesn't exist and isn't the pattern here; authorization does use `app/Policies` for per-instance abilities (see providers.md).
