# Branching & Release Model

This is the single source of truth for how code moves from a developer's
machine to Packagist. It applies to both repositories:

- **`libxa-framework/libxa`**: the framework (a Composer *library*)
- **`libxa-framework/LibxaStack`**: the starter kit (a Composer *project*)

---

## The one rule that matters

> **`main` is what Packagist publishes. Nothing lands on `main` except through
> a `release/*` or `hotfix/*` pull request.**

Every commit on `main` is, by definition, production. Treat it accordingly.

---

## The branches

```
  feature/auth-guards ─┐
  fix/router-405 ──────┼──▶ develop ──▶ release/v0.9.0 ──▶ main ──(tag v0.9.0)──▶ Packagist
  docs/contributing ───┘        ▲                            │
                                │                            │
                                └────── back-merge ──────────┤
                                                             │
                          hotfix/v0.8.1 ────────────────────▶─┘
```

| Branch | Cut from | Merges into | Lifetime | Protected |
|---|---|---|---|---|
| `main` |: |: | permanent | ✅ yes |
| `develop` | `main` |: | permanent | ✅ yes |
| `feature/*` | `develop` | `develop` | days | no |
| `fix/*` | `develop` | `develop` | hours–days | no |
| `docs/*`, `chore/*`, `test/*`, `refactor/*` | `develop` | `develop` | hours–days | no |
| `release/vX.Y.Z` | `develop` | `main` **and** `develop` | days | no |
| `hotfix/vX.Y.Z` | `main` | `main` **and** `develop` | hours | no |

### `main`

The published branch. Packagist serves `dev-main` from its tip and a tagged
release from every `vX.Y.Z` tag. It must always be green.

Direct pushes are disabled. Force-pushes are disabled. History is permanent.

### `develop`

The integration branch. This is where day-to-day work accumulates between
releases and where CI runs the full matrix. `develop` may briefly be ahead of
`main`; it must never be *behind* it: see [Back-merging](#back-merging).

### `feature/*`, `fix/*`, and friends

Short-lived. Branch from `develop`, open a PR back into `develop`, delete on
merge. Keep them small: a branch open longer than a week is a rebase problem
waiting to happen.

Name them after the change, not the person or the ticket alone:

```
feature/route-model-binding
fix/csrf-array-token
docs/release-runbook
chore/bump-phpunit
```

### `release/vX.Y.Z`

Cut when `develop` holds everything the next version should contain. From this
point `develop` is open for the version *after* next, while the release branch
takes only stabilisation commits: version bumps, changelog, documentation, and
fixes for bugs found during release testing. **No new features.**

### `hotfix/vX.Y.Z`

The only branch that starts from `main`. For a production defect that cannot
wait for the next release. Merges into `main` (tag immediately) **and** back
into `develop`.

---

## Version numbering

[Semantic Versioning 2.0.0](https://semver.org). Tags are prefixed with `v`.

While the framework is pre-1.0 (`0.x.y`), Composer treats the **minor** number
as the breaking-change boundary: `^0.8.0` allows `0.8.9` but not `0.9.0`. So:

| Change | Pre-1.0 bump | Post-1.0 bump |
|---|---|---|
| Breaking API change | `0.8.3` → `0.9.0` | `1.4.2` → `2.0.0` |
| New backward-compatible feature | `0.8.3` → `0.8.4` | `1.4.2` → `1.5.0` |
| Bug fix | `0.8.3` → `0.8.4` | `1.4.2` → `1.4.3` |

Because a pre-1.0 patch and a pre-1.0 feature share the same slot, **anything
that breaks a documented API needs a minor bump**, even if it looks small.

---

## Back-merging

After **every** merge into `main`, whether release or hotfix, `main` must be merged
back into `develop` immediately:

```bash
git checkout develop
git pull origin develop
git merge --no-ff origin/main
git push origin develop
```

Skip this and the next release branch will silently revert the hotfix. The
release workflow prints a reminder, and `Back-merge main into develop` is a
required checkbox on the release PR template.

---

## What CI enforces

| Workflow | Runs on | Blocks merge |
|---|---|---|
| `ci.yml` | PRs into `develop`/`main`, pushes to both | ✅ |
| `release.yml` | `v*` tags | n/a (publishes) |

`ci.yml` runs the full test suite on PHP 8.3 and 8.4, lints every source file,
and asserts the PSR-4 autoloading contract. A red build cannot be merged.

---

## Branch protection settings

Configure these once per repository under
**Settings → Branches → Add branch ruleset**. They are the mechanism that makes
the model above real rather than aspirational.

### `main`

- ✅ Require a pull request before merging
  - Required approvals: **1** (2 once the team is larger than three)
  - ✅ Dismiss stale approvals when new commits are pushed
- ✅ Require status checks to pass: select every `ci.yml` matrix job
  - ✅ Require branches to be up to date before merging
- ✅ Require conversation resolution before merging
- ✅ Require linear history
- ✅ Block force pushes
- ✅ Restrict deletions
- ✅ Do not allow bypassing the above settings *(including for admins)*
- Restrict who can push: the release-manager team only

### `develop`

Same as `main`, with two relaxations:

- Required approvals: **1**
- Linear history: optional (merge commits from feature branches are fine)

### Tag protection

Protect the `v*` pattern so only the release-manager team can create release
tags. A tag is what triggers publication, so it deserves the same care as a
push to `main`.

---

## Packagist

Both packages update through a **GitHub webhook**, configured once:

1. Packagist → your package → *Settings* → copy the API token.
2. GitHub repo → *Settings* → *Webhooks* → *Add webhook*
   - Payload URL: `https://packagist.org/api/github?username=<packagist-user>`
   - Content type: `application/json`
   - Secret: your Packagist API token
   - Events: *Just the push event* (this covers tags)

After that, pushing a tag publishes a release and pushing to `main` refreshes
`dev-main`. If a release does not appear within a minute, check
*Settings → Webhooks → Recent Deliveries* for the response body: Packagist
reports the reason there.

---

## Frequently asked

**Why not just commit to `main`?**
Because `main` is published. A half-finished merge on `main` is a broken
release for every consumer running `dev-main`, and one careless tag makes it
permanent: Packagist releases cannot be meaningfully retracted.

**Can I tag from `develop`?**
No. Tags come from `main` only, so the published history is always a strict
subset of `main`. `release.yml` fails a tag that is not an ancestor of `main`.

**A release is stuck in QA and an urgent fix is needed.**
That is what `hotfix/*` is for: branch from `main`, not from the release branch.

**Who cuts releases?**
Whoever holds the release-manager role that cycle. See
[RELEASING.md](RELEASING.md) for the runbook.
