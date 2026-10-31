---
paths:
  - 'lang/**'
---

# Lang

## Localization uses short array keys
Use short keys in `lang/en/{group}.php` (e.g. `admin.users`) referenced via `__('group.key')` — not JSON string/full-sentence keys.

## UI labels are sentence case
Every user-facing string is sentence case: capitalise only the first word, proper nouns (Google) and acronyms (2FA, DNS). "Save changes", "My profile", "Admin dashboard", never "Save Changes". Applies to lang files (here and packages/teams/lang), literal __() strings, Blade text and Filament labels. In the teams lang file a label placeholder is `:Team` only at the start of a string and `:team` elsewhere (TeamLabels fills it in lower case; `:Team` capitalises). When a string joins two labels, separate them ("Select all: :group") rather than changing the inner label's case. Settled 2026-10-31 (#16).
