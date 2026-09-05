---
paths:
  - 'tests/**'
---

# Tests

## Tests use RefreshDatabase
Feature and Unit tests use the `RefreshDatabase` trait for database isolation between runs.

## Factories with named states, no seeders in tests
Build test fixtures with model factories and their custom state methods (e.g. `User::factory()->superAdmin()->unverified()`) — don't call `$this->seed()` in tests.
