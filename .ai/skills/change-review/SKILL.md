---
name: change-review
description: 'Review a change set before it is offered for commit, push or PR. Use whenever a change is ready (code, views, CSS, tests, docs or rules), before saying it is ready to commit, and again after fixing review findings. Covers the ten review areas (UI consistency among them), how to present findings, the re-review loop, and the browser checks for visual or keyboard changes.'
---

# Change review

Every change set is reviewed before it is offered for commit, push or PR.
This is a boilerplate inherited by every project started from it (see
`.github/copilot-instructions.md`), so a shortcut taken once ships everywhere.

## When

- Before saying a change is ready to commit. No exceptions for small changes,
  test-only changes, docs or rules.
- Again after fixing findings, every time, however small the fix. Scale the
  re-review to the fix: a three-line follow-up gets a quick read of those lines,
  not a full pass.
- When porting a change to a project built on this boilerplate, review the port
  too: the repos diverge, and a clean cherry-pick can still miss a copy.

## Who

A separate reviewer, not the author. In Claude Code that is a sub-agent on a
high-capability model given the diff, the issue or brief, the relevant
`.ai/rules` files and this checklist; it reads and runs checks but never edits
or commits. Give it the context it can't infer: what you verified and how,
what you chose not to do and why, and any consumer the change will be ported to.

Where no sub-agent is available, review in a separate pass after the work is
done: re-read the whole diff cold against this checklist, and say in the
findings that it was a self-review.

## The ten areas

The reviewer reports on every one, saying plainly where an area is clean.

1. **Correctness**: does it do what the issue asks, in every case and state
   (empty, error, permission denied, re-render, other layouts and areas)?
2. **Security**: authorisation and validation at the boundary, no bypassable
   guard, nothing exposed to a session that shouldn't see it.
3. **Deprecated calls**: APIs current for the installed versions
   (`composer show`, `package.json`, `search-docs`).
4. **Accessibility** (where relevant): names, roles, focus, keyboard, contrast
   (WCAG AA), screen-reader announcements.
5. **Performance**: query counts, N+1, work repeated per request or per render.
6. **Test validity**: does the test fail without the fix? Revert only the
   non-test files (e.g. `git stash push -- app resources`), run the test, then
   `git stash pop`; a sub-agent reviewer does this in its own worktree, or asks
   the author to. Does it assert behaviour rather than exact markup, and cover
   the failure modes that matter, without padding?
7. **Consistency**: follows the matching `.ai/rules` files, sibling files and
   the design-system skill; no em dashes.
8. **Reinventing the wheel**: does the framework, a package already installed,
   or an existing component or helper already do this?
9. **Over-engineering**: is every new piece paying for itself?
10. **UI consistency** (anything a user sees): does it look, sit and read like
    the rest of the app? Compare each new or changed screen with its nearest
    sibling (a new admin list with Services or Users, a new dialog with an
    existing form, a banner with the banners already shipped), side by side
    in screenshots, not from memory:
    - **Placement**: the primary action where siblings put it (page header,
      or the table toolbar per `.ai/rules/tables.md`), row actions in the ⋮
      menu, filters and segmented controls in the same slot, Cancel and the
      submit button in the same order, banners in the same place.
    - **Layout**: page header and description, card and table chrome, spacing,
      modal widths, field grids, empty states; nothing new that a sibling
      solves differently, and it still works at 375 px.
    - **Language**: the same word for the same thing everywhere (Add, Edit,
      Delete or Retire, salon, closed), sentence case, plain short sentences
      for the people using it, no jargon, no em dashes; a message says what
      to do next.
    - **Format**: dates, times, counts, plurals and names written the way
      siblings write them (the existing formatters and lang strings, not a
      new pattern).

## Presenting findings

Show the findings to the user with the work, before changing anything:

- Rank them by severity (high, medium, low, nit), each with a one-line plain
  description and a proposed response (fix, defer to an issue, or leave, with why).
- Say what the reviewer confirmed and which areas were clean.
- Anything outside the change's concern goes in a new GitHub issue, not this
  change set (one PR, one concern).
- Then iterate on what the user decides, not before. Low-value polish can be
  skipped when the user says so; say what it would cost and what it buys.
- When the findings are resolved and re-reviewed, say the change is ready and
  stop. Never commit, push or open a PR without an explicit request in that
  message.

## Browser checks for visual or keyboard changes

A PHP test can't see the rendered page. For a change to how something looks
or behaves in the browser, check it in a headless browser:
`npm i --prefix <scratch dir> playwright axe-core` (never into the project).
Playwright reuses an already-downloaded Chromium if its version matches;
otherwise run `npx playwright install chromium` there.

- **Contrast**: run axe-core's `color-contrast` rule over the affected pages in
  light and dark. It skips placeholders and anything only shown on
  interaction, so check those directly.
- **Names and roles**: read the accessibility tree (CDP
  `Accessibility.getFullAXTree`) with the component open, not just the markup.
- **Keyboard and focus**: drive it with Tab, Enter and Escape and record where
  focus goes.
- **Looks**: before and after screenshots of each changed element, at desktop
  and at a phone width (375 px), so the user can judge the visual change.
  Give the reviewer the sibling screens too, for area 10.
  Rebuild assets first (`npm run build`):
  a class used for the first time isn't in the CSS until then.
- Give the user links to the pages to look at themselves (resolve them with
  Boost `get-absolute-url`).
