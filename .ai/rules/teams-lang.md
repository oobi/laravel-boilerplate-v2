---
paths:
  - 'packages/teams/lang/**'
---

# Teams Lang

## Never put an article or count word in front of a label placeholder
`:team`/`:member`/`:owner` are substituted verbatim from `config('teams.labels')`, so "a :team" renders "a organisation" and "Add a :member" renders "Add a staff". Reword around the placeholder instead: "any :team", "every :team", "this :team", a plural ("You can't create :teams"), or a pronoun with an antecedent ("…add you to one"). `TeamRelabelTest::test_no_string_puts_an_article_before_a_label_placeholder` scans the whole `teams::teams` group and fails on any `a`/`an` before a placeholder — it is the guard, so a new string that needs an article must be reworded, not excepted.
