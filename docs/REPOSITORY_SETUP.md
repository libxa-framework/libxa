# Repository Setup: one-time checklist

Everything in this document must be done **in the GitHub UI** by someone with
admin rights. It cannot be committed, so it is easy to forget, and until it is
done, the branch model in [BRANCHING.md](BRANCHING.md) is only a convention
rather than something enforced.

Do this for **both** repositories:

- `libxa-framework/libxa` (the framework)
- `libxa-framework/LibxaStack` (the starter kit)

---

## 1. Push the branches

```bash
git push -u origin main
git push -u origin develop
```

Then **Settings → General → Default branch → `develop`**.

> Making `develop` the default matters more than it looks. GitHub targets new
> pull requests at the default branch, so leaving it on `main` means every
> contributor's first PR aims at the published branch and has to be redirected.

---

## 2. Branch protection

**Settings → Rules → Rulesets → New branch ruleset**

### Ruleset: `main`

| Setting | Value |
|---|---|
| Target | `refs/heads/main` |
| Enforcement | Active |
| Restrict deletions | ✅ |
| Block force pushes | ✅ |
| Require linear history | ✅ |
| Require a pull request | ✅: 1 approval (2 once the team exceeds three people) |
| Dismiss stale approvals on new commits | ✅ |
| Require conversation resolution | ✅ |
| Require status checks | ✅: see below |
| Require branches to be up to date | ✅ |
| Bypass list | **empty**, including admins |

Required status checks (add each by name once CI has run at least once):

- `Tests · PHP 8.3`
- `Tests · PHP 8.4`
- `Security audit`
- Framework only: `PSR-4 contract`, `Distribution archive`
- Starter kit only: `Skeleton hygiene`

> An empty bypass list is the point of the exercise. "Admins can bypass" means
> the rules hold exactly until the moment someone is in a hurry, which is
> precisely when they are load-bearing.

### Ruleset: `develop`

Same, with two relaxations:

- Require linear history: ❌ (merge commits from feature branches are fine)
- Approvals: 1

### Ruleset: tags

| Setting | Value |
|---|---|
| Target | `refs/tags/v*` |
| Restrict creation | ✅: release managers only |
| Restrict updates | ✅ |
| Restrict deletions | ✅ |

A tag is what triggers publication to Packagist, and a published release cannot
meaningfully be retracted. It deserves the same protection as a push to `main`.

---

## 3. Packagist

Register each package once at <https://packagist.org/packages/submit>:

| Repository | Package |
|---|---|
| `libxa-framework/libxa` | `libxa/framework` |
| `libxa-framework/LibxaStack` | `libxa/libxa` |

Then wire the auto-update webhook so you never have to press "Update" by hand:

1. Packagist → *Profile* → *Show API Token*, and copy it.
2. GitHub repo → **Settings → Webhooks → Add webhook**
   - Payload URL: `https://packagist.org/api/github?username=<your-packagist-username>`
   - Content type: `application/json`
   - Secret: your Packagist API token
   - SSL verification: enabled
   - Events: **Just the push event** (this covers tag pushes too)

**Verify it works before you rely on it.** Push any commit to `main`, then check
*Settings → Webhooks → Recent Deliveries* for a `200`. A silently misconfigured
webhook looks identical to a working one until release day.

---

## 4. Security features

**Settings → Code security and analysis**, enable on both repositories:

- ✅ Private vulnerability reporting: this is what `SECURITY.md` points people at
- ✅ Dependency graph
- ✅ Dependabot alerts
- ✅ Dependabot security updates

`.github/dependabot.yml` already handles version updates and targets `develop`.

---

## 5. Discussions

**Settings → General → Features → Discussions** on the **framework** repository.

The issue templates route "How do I …?" there, so questions stop competing with
confirmed defects in the issue tracker. Leave it off for the starter kit: its
templates point at the framework's Discussions.

---

## 6. Labels

The issue templates apply `bug`, `enhancement` and `needs-triage`. GitHub
creates the first two; add the rest:

| Label | Colour | For |
|---|---|---|
| `needs-triage` | `#ededed` | Not yet looked at |
| `dependencies` | `#0366d6` | Dependabot |
| `ci` | `#0366d6` | Workflow changes |
| `security` | `#b60205` | Security-relevant (never for an undisclosed vulnerability) |
| `breaking` | `#d93f0b` | Requires a minor bump pre-1.0 |
| `good first issue` | `#7057ff` | Small and well-specified |
| `frontend` | `#c5def5` | Starter kit npm dependencies |

---

## 7. Teams and access

| Team | Permission | Can |
|---|---|---|
| `maintainers` | Admin | Change settings, manage releases |
| `release-managers` | Write + tag creation | Cut releases (see [RELEASING.md](RELEASING.md)) |
| `contributors` | Write | Push branches, open PRs: **not** merge to `main` |

Everyone else contributes by fork and pull request, which needs no access at
all.

---

## 8. Verify it is real

Do not assume. Confirm each of these:

- [ ] `git push origin main` from a local clone is **rejected**
- [ ] A PR into `main` cannot merge while CI is red
- [ ] A PR into `main` cannot merge without an approval
- [ ] A non-release-manager cannot create a `v*` tag
- [ ] A push to `main` produces a `200` in Recent Deliveries
- [ ] New PRs default to targeting `develop`
- [ ] The private vulnerability reporting form opens from the Security tab

The last one matters more than it appears: `SECURITY.md` tells researchers to
use it, and if it is disabled they will fall back to opening a public issue,
which is a disclosure.

---

## 9. First release after this setup

Once everything above is done, follow [RELEASING.md](RELEASING.md). The first
run is a good moment to confirm `release.yml` actually blocks a bad tag: try
tagging something that is *not* on `main` and check that the workflow fails as
designed.
