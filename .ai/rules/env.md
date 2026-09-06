---
paths:
  - .env
  - .env.example
---

# Environment files

## `.env.example` must stay in sync with `.env`, with comments
Whenever a key is added to (or removed from) `.env` — new package config, feature flag, credential, driver option, etc. — make the same change to `.env.example` in the same commit. Use a placeholder or safe default value in `.env.example`, never a real secret. Add a short one-line comment above or beside the key explaining what it's for, and note valid values or gating behavior when it isn't obvious (e.g. "leave unset to auto-follow APP_DEBUG"). Group related keys under a `# ---- Section ----` header if starting a new section, matching the existing layout in `.env.example`.
