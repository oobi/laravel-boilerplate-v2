# Claude Code entrypoint

This project's agent instructions are shared with GitHub Copilot rather than
duplicated. Both files below are imported verbatim — edit *them*, not this file.

@AGENTS.md
@.github/copilot-instructions.md

## Notes for Claude Code specifically

- Skills live in `.ai/skills/` — the agent-agnostic source of truth, alongside
  `.ai/rules/`. Both `.claude/skills` and `.github/skills` are symlinks to it,
  so every agent loads the same nine skills. Add or edit skills in `.ai/skills/`,
  never through a symlink.
- Relative links inside these files resolve from *their own* location:
  `.github/copilot-instructions.md` uses `../docs/...` (= `docs/` at the repo
  root), and each `SKILL.md` uses `../../../docs/...` (also the repo root).
- Laravel Boost's MCP server is wired up in `.mcp.json`. If `search-docs`,
  `database-query`, `database-schema`, or `tinker` aren't available, the server
  didn't start — say so rather than falling back to raw SQL or shell guesswork.

## Comment Policy
- **No em dashes**: never use em dashes in code, docs, or copy. Use periods, commas, colons, or rewrite the sentence.


## Git

- Keep comments brief and to the point.
- **ALWAYS merge with `--no-ff`.** Never fast-forward a branch into `main` (or
  any shared branch): every merge MUST create a merge commit, *even when a
  fast-forward is possible*. Always run `git merge --no-ff <branch>` — never a
  bare `git merge` or `--ff-only`. If you catch yourself about to fast-forward,
  stop and re-merge with `--no-ff`.
