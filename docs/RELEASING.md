# Release Runbook

For the maintainer cutting a release. Read
[BRANCHING.md](BRANCHING.md) first if you have not.

Every release is **manually tagged**; CI verifies the tag rather than creating
it. That means nothing ships by accident, and the person who tagged it is
accountable for it.

---

## Before you start

- [ ] `develop` is green on CI
- [ ] Every PR intended for this version is merged into `develop`
- [ ] You know the version number and why (see the SemVer table in BRANCHING.md)
- [ ] You have push rights to `main` and permission to create `v*` tags

---

## 1. Cut the release branch

```bash
git checkout develop
git pull origin develop
git checkout -b release/v0.9.0
```

From now on `develop` is open for the *next* version. Only stabilisation
commits go on this branch.

## 2. Update the changelog

Move everything under `## [Unreleased]` in `CHANGELOG.md` into a new section:

```markdown
## [0.9.0] - 2026-08-15
```

Then re-add an empty `## [Unreleased]` heading above it, and update the link
definitions at the bottom of the file.

Write the entries for the person **upgrading**, not for the person who wrote
the code. "Fixed `Router::url()` throwing on missing parameters" is useful;
"fixed router bug" is not.

## 3. Verify locally

```bash
composer install
composer test
composer lint
```

For the starter kit, also run through a real installation:

```bash
composer create-project libxa/libxa /tmp/smoke-test
cd /tmp/smoke-test && php libxa migrate && php vendor/bin/phpunit
```

## 4. Open the release PR

```bash
git push -u origin release/v0.9.0
gh pr create --base main --head release/v0.9.0 \
  --title "Release v0.9.0" \
  --body-file .github/RELEASE_PR_TEMPLATE.md
```

Get it reviewed. The reviewer's job is to read the changelog against the diff
and confirm the version bump is right — particularly whether anything in there
is breaking.

## 5. Merge and tag

Merge the PR into `main`. Then, **on `main`**:

```bash
git checkout main
git pull origin main

git tag -a v0.9.0 -m "Release v0.9.0"
git push origin v0.9.0
```

Annotated tags (`-a`) only — `release.yml` rejects lightweight tags, because
they carry no author, date, or message.

## 6. Watch the release workflow

`release.yml` fires on the tag and will fail the release if any of these do not
hold:

| Check | Why |
|---|---|
| Tag is an ancestor of `main` | Publishing something never reviewed on `main` |
| Tag is annotated | Lightweight tags have no provenance |
| Tag matches `composer.json` version constraints | Catches a forgotten bump |
| `CHANGELOG.md` has a section for this version | Catches a forgotten changelog |
| Full test suite passes at the tagged commit | The tag is what users get |

On success it creates the GitHub Release with the changelog section as its
body.

## 7. Confirm Packagist

Check <https://packagist.org/packages/libxa/framework> for the new version.
It normally appears within seconds of the tag push.

If it does not: **GitHub repo → Settings → Webhooks → Recent Deliveries**.
Packagist puts the reason in the response body.

## 8. Back-merge into develop

Not optional. Skip it and the next release silently reverts this one.

```bash
git checkout develop
git pull origin develop
git merge --no-ff origin/main
git push origin develop
```

## 9. Clean up

```bash
git push origin --delete release/v0.9.0
git branch -d release/v0.9.0
```

---

## Hotfix

For a production defect that cannot wait. Same as above, but shorter and
starting from `main`:

```bash
git checkout main
git pull origin main
git checkout -b hotfix/v0.8.1

# fix it, and add a regression test that fails without the fix
# update CHANGELOG.md

git push -u origin hotfix/v0.8.1
gh pr create --base main --head hotfix/v0.8.1 --title "Hotfix v0.8.1"
```

Merge → tag on `main` → **back-merge into `develop`** → delete the branch.

A hotfix without a regression test is not finished. The whole reason it is
urgent is that nothing caught it.

---

## Releasing the two repositories together

The framework and the starter kit version independently, but a framework
release usually needs a starter-kit follow-up so new projects get the new
version.

**Order matters:**

1. Release `libxa/framework` first and confirm it is live on Packagist.
2. In `LibxaStack`, widen the constraint in `composer.json`:
   ```json
   "libxa/framework": "^0.9.0 || dev-main@dev"
   ```
3. `composer update libxa/framework`, run the suite, commit the lock file.
4. Release the starter kit.

Step 2 must not drop the `dev-main@dev` half: that is what lets a local sibling
checkout of the framework be used during development. See the "Developing
against a local framework checkout" section of the starter kit's README.

> **Do not** widen the constraint before the framework tag is live. Composer
> will resolve to the older release and the lock file will pin the wrong
> version.

---

## If you tagged the wrong thing

Do not delete a published tag. Packagist consumers may already have it in a
lock file, and deleting it breaks their builds without warning.

Instead, tag the fix forward:

```bash
# v0.9.0 was broken; ship v0.9.1 immediately
git checkout -b hotfix/v0.9.1 main
# ... fix ...
```

Then mark the bad release as broken in `CHANGELOG.md` so the next person
reading the history understands why the window was so short:

```markdown
## [0.9.0] - 2026-08-15 — **BROKEN, use 0.9.1**
```

The single exception is a tag pushed within a minute or two that Packagist has
not yet picked up and nobody could have consumed. Even then, prefer moving
forward.
