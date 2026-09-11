---
paths:
  - 'tests/Feature/Scenarios/**'
---

# Scenarios

## Scenario tests cover each supported config recipe — extend all of them together
tests/Feature/Scenarios/ holds one class per supported app shape from docs/config-recipes.md: VanillaAppTest (no teams tier — simulated by LoginRedirectRegistry::flush()), PublicSaasTest, InvitationOnlyTest, BackofficeTest. Each pins its full config in setUp() via config([...]) (overriding the phpunit.xml defaults) and walks only the config-SENSITIVE junctions — creation gate, invitation surface, login landing, onboarding copy. This is how a boilerplate guards against "works in the default config, breaks under another". Rule: when you add or change a config-sensitive feature, add its assertion to EVERY relevant scenario class, not just the default suite — otherwise a recipe silently regresses. Test the supported recipes, not the full config matrix. Do NOT reintroduce a hand-maintained config-coherence validator/command: it rots and gives false confidence — scenario tests fail on real behaviour instead.
