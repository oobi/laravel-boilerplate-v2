---
paths:
  - 'app/Models/**'
---

# Models

## Use modern Attribute class for accessors, not legacy getXxxAttribute()
New accessors/mutators use `protected function xxx(): Attribute` (e.g. `Attribute::get(...)`), not legacy `getXxxAttribute()`/`setXxxAttribute()` magic methods. `User.php` still has legacy accessors from before this was settled — don't add more, migrate opportunistically.
