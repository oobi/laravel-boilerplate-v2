---
paths:
  - 'app/**'
---

# App

## Prefer collect() chains over array_map/foreach
Use fluent `collect()->map()->filter()...` chains for collection transformations instead of `array_map`/`array_filter` or manual `foreach`.
