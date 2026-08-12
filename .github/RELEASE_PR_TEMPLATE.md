# Release vX.Y.Z

<!--
Used for release/* and hotfix/* pull requests into main.
Open with:  gh pr create --base main --body-file .github/RELEASE_PR_TEMPLATE.md
Runbook:    docs/RELEASING.md
-->

## Version

**vX.Y.Z**: <!-- patch | minor | major -->

<!--
Below 1.0, Composer treats the MINOR number as the compatibility boundary:
^0.8.0 allows 0.8.9 but not 0.9.0. Anything breaking a documented API needs a
minor bump even if the diff looks small.
-->

## Contents

<!-- Paste the CHANGELOG section for this version. -->

## Pre-merge checklist

- [ ] Branched from `develop` (or from `main` if this is a hotfix)
- [ ] `CHANGELOG.md` has a `## [X.Y.Z] - YYYY-MM-DD` section
- [ ] Link definitions at the bottom of `CHANGELOG.md` updated
- [ ] Full suite green on PHP 8.3 and 8.4
- [ ] `composer validate --strict` passes
- [ ] Version bump matches the nature of the changes (see above)
- [ ] Breaking changes have migration notes
- [ ] Reviewer has read the changelog **against the diff**

## Post-merge checklist

Do these in order: see `docs/RELEASING.md`.

- [ ] Annotated tag pushed **from `main`**: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`
- [ ] `release.yml` green
- [ ] Version visible on Packagist
- [ ] **Back-merged `main` into `develop`** (skip this and the next release reverts it)
- [ ] Release branch deleted
- [ ] Starter kit constraint widened, if this is a minor or major release
