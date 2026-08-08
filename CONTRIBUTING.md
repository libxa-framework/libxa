# Contributing to LibxaFrame

Thanks for helping. This document covers everything from a local checkout to a
merged pull request.

- [Getting set up](#getting-set-up)
- [Where to branch from](#where-to-branch-from)
- [Commit messages](#commit-messages)
- [Coding standards](#coding-standards)
- [Tests](#tests)
- [Opening a pull request](#opening-a-pull-request)
- [Reporting a bug](#reporting-a-bug)
- [Reporting a vulnerability](#reporting-a-vulnerability)

---

## Getting set up

Requirements: **PHP 8.3+** (8.4 supported and tested) with `mbstring`, `xml`,
`ctype`, `iconv`, `intl`, `pdo_sqlite` and `openssl`; plus Composer 2.

```bash
git clone https://github.com/libxa-framework/libxa.git
cd libxa
composer install
composer test
```

You should see a green suite. If you do not, that is a bug — please open an
issue before changing anything.

### Working on the framework and the starter kit together

Most framework changes are easiest to validate against a real application.
Clone both repositories as **siblings**:

```
your-workspace/
├── libxaframe/     # this repository (git clone .../libxa.git libxaframe)
└── LibxaStack/     # git clone .../LibxaStack.git
```

Then in `LibxaStack`, `composer install` junctions `vendor/libxa/framework`
straight onto `../libxaframe`. The vendor directory *is* your framework working
copy, so framework edits — including brand-new classes — take effect on the
next request with no `composer update` and no `dump-autoload`.

Run both suites before pushing:

```bash
(cd libxaframe && composer test)
(cd LibxaStack && composer test)
```

---

## Where to branch from

**Always `develop`.** Never `main` — that is the published branch and is
protected.

```bash
git checkout develop
git pull origin develop
git checkout -b fix/csrf-array-token
```

| Prefix | For |
|---|---|
| `feature/` | new functionality |
| `fix/` | bug fixes |
| `docs/` | documentation only |
| `test/` | tests only |
| `refactor/` | no behaviour change |
| `chore/` | tooling, dependencies, CI |
| `perf/` | performance work |

The single exception is a **hotfix** for a live production defect, which
branches from `main`. Those are coordinated by maintainers — open an issue
first rather than sending one unannounced.

The full model is in [docs/BRANCHING.md](docs/BRANCHING.md).

---

## Commit messages

[Conventional Commits](https://www.conventionalcommits.org):

```
<type>(<optional scope>): <description>

[optional body]

[optional footer]
```

Types: `feat`, `fix`, `docs`, `test`, `refactor`, `chore`, `perf`, `build`, `ci`.

```
fix(router): match optional parameters without a trailing slash
feat(validation): add numeric-aware min/max sizing
docs(branching): document the back-merge step
```

A breaking change gets a `!` and a footer explaining the migration:

```
feat(request)!: normalise header keys to upper snake case

BREAKING CHANGE: Request::header() previously returned '' for any
multi-word header name. Code that relied on that empty return — for
example `if ($request->header('Content-Type') === '')` — must be
updated to check the real value.
```

Write the description in the imperative: "add", not "adds" or "added". The body
is for **why**, since the diff already shows what.

---

## Coding standards

- **PSR-12**, with `declare(strict_types=1);` in every file.
- **One class per file**, named after the file, in the PSR-4 namespace matching
  its directory. This is enforced by `tests/Feature/AutoloadingTest.php` — it is
  not a style preference. A class the autoloader cannot find is a fatal error at
  runtime, and a class declared in two files is a `Cannot redeclare class` fatal.
- Type-hint everything: parameters, returns and properties.
- Prefer a clear exception over a silent fallback. Most of the worst bugs found
  in the August 2026 audit were places that swallowed a failure and carried on
  with wrong data.

Check your work:

```bash
composer lint    # php -l over every source file
composer test
```

### Comments

Comment the **why**, not the what. A comment that restates the code is noise; a
comment explaining a non-obvious constraint is worth its weight.

```php
// Bad — the code already says this
// Loop over the routes
foreach ($this->routes as $route) {

// Good — explains something the code cannot
// Registration order matters: /posts/create must be checked before
// /posts/{id}, or "create" is swallowed as an {id}.
```

---

## Tests

**Every bug fix needs a regression test that fails without the fix.** Verify it
does by stashing your change and re-running the test.

```
tests/
├── TestCase.php    # boots a real Application against a throwaway skeleton
├── Unit/           # a single class, no container needed
└── Feature/        # several units together, or a real booted application
```

- Unit tests extend `PHPUnit\Framework\TestCase` directly.
- Feature tests extend `Tests\TestCase`, which gives you a real booted
  `Application`, `makeRequest()`, and per-test isolation.
- Database tests run against real in-memory SQLite. Do not mock PDO — the point
  is to catch SQL that does not parse.

Name tests as sentences describing the behaviour:

```php
public function test_optional_parameter_matches_with_and_without_the_segment(): void
```

When a test exists because of a specific bug, say so in a docblock. Future
maintainers need to know which assertions are load-bearing:

```php
/**
 * "/users/{id?}" compiled to "/users/(?P<id>[^/]+)?" — the slash was
 * mandatory, so the "no parameter" case could never match.
 */
```

Run a subset while iterating:

```bash
composer test -- --filter RouterTest
composer test -- --testsuite Unit
```

---

## Opening a pull request

1. Rebase onto the current `develop`:
   ```bash
   git fetch origin
   git rebase origin/develop
   ```
2. Push and open the PR **against `develop`**.
3. Fill in the template — particularly *how you verified it*. "Tests pass" is
   not verification; "added `test_x`, confirmed it fails on `develop`" is.
4. Keep it focused. A PR that fixes a bug *and* reformats 40 files cannot be
   reviewed properly, and will be sent back.

### What reviewers look for

- Does it do what the description says, and only that?
- Is there a test that would have caught the bug?
- Does it break a documented API? If so, is that called out and is the version
  bump right?
- Are error paths handled, or does a failure silently produce wrong data?

### After approval

A maintainer merges. Do not merge your own PR unless you are the only
maintainer awake and it is a hotfix.

---

## Reporting a bug

Open an issue with the **Bug report** template. The three things that determine
whether it can be fixed:

1. **Reproduction** — the smallest code that shows the problem.
2. **Expected vs actual**, precisely. "It breaks" is not actionable.
3. **Environment** — PHP version, framework version, OS, database driver.

If you can write a failing test, attach it. That is the single most useful
thing you can send.

---

## Reporting a vulnerability

**Do not open a public issue.** See [SECURITY.md](SECURITY.md) for the private
disclosure process.

---

## Licence

By contributing you agree that your contributions are licensed under the
[MIT Licence](LICENSE), the same as the project.
