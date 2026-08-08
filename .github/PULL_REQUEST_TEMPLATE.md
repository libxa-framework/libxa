<!--
Target branch: `develop` for everything except a coordinated hotfix or release.
A PR opened against `main` from a feature branch will be closed — see
docs/BRANCHING.md.
-->

## What does this change?

<!-- One or two sentences. What behaviour is different after this merge? -->

## Why?

<!-- The problem being solved. Link the issue: "Fixes #123". -->

## How did you verify it?

<!--
"Tests pass" is not verification — CI already tells us that.

Useful:
  "Added test_router_matches_optional_parameter; confirmed it fails on develop
   and passes with this change."
  "Ran the starter kit end to end: register -> login -> /home -> logout."
-->

## Type of change

- [ ] Bug fix (non-breaking)
- [ ] New feature (non-breaking)
- [ ] **Breaking change** — existing code must be updated
- [ ] Documentation
- [ ] Refactor / tooling / CI

## Checklist

- [ ] Branched from `develop` and rebased onto current `develop`
- [ ] `composer test` passes locally
- [ ] `composer lint` passes locally
- [ ] **A regression test that fails without this change** (required for bug fixes)
- [ ] One class per file, matching its PSR-4 path
- [ ] `CHANGELOG.md` updated under `## [Unreleased]`
- [ ] Commit messages follow Conventional Commits
- [ ] Public API changes are documented

## Breaking change details

<!--
Delete this section if nothing breaks.

Otherwise: what breaks, how a user knows they are affected, and the migration.
Remember that below 1.0 Composer treats the MINOR number as the compatibility
boundary, so a breaking change needs a minor bump, not a patch.
-->
