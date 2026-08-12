# Changelog

All notable changes to LibxaFrame are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Pre-1.0 note.** Composer treats the *minor* number as the compatibility
> boundary below 1.0: `^0.8.0` allows `0.8.9` but not `0.9.0`. Anything that
> breaks a documented API therefore requires a minor bump, not a patch.

> The detailed engineering write-up of the July and August 2026 stability
> audits (every bug, why it mattered, and how it was fixed) lives in
> [CHANGES.md](CHANGES.md). This file is the user-facing summary.

## [Unreleased]

## [0.10.2] - 2026-08-12

### Fixed

- **A package could not ship a migration.** `ServiceProvider::loadMigrationsFrom()`
  is guarded by `$this->app->has('migrator')`, and nothing ever bound
  `migrator`, so the method silently did nothing. Every package that followed
  the documented API shipped a migration that could never run, and said so
  nowhere. `migrator` is now a shared binding, registered by the database
  provider with the application path already added.
- **`migrate` threw away what packages registered.** The command constructed
  its own `Migrator`, so any path a service provider added during boot was
  discarded before the command ever looked. It now resolves the shared
  instance.
- `Migrator::addPath()` ignores a path it already has. With several places
  contributing paths, a duplicate meant running every migration in it twice.

## [0.10.1] - 2026-08-12

Presentation only. No API, behaviour or dependency changed, so `^0.10.0`
picks this up without any action.

### Changed

- Em dashes are gone from the source, the comments and the documentation,
  replaced by a colon, a period or a comma according to what the sentence was
  actually doing.
- The built-in error pages use the standard unpunctuated HTTP status phrases
  (`404 Not Found`, `405 Method Not Allowed`, `500 Server Error`), and their
  titles read `404 | LibxaFrame` rather than `404: LibxaFrame`. The 405 page
  lists the permitted methods as `Allowed: GET, POST` instead of the
  double-colon `try:` phrasing an earlier mechanical pass left behind.

## [0.10.0] - 2026-08-08

### Added

- **PHP 8.5 support.** `^8.3` already permitted it, but nothing tested it, so
  support was a claim rather than a fact. CI now runs the full suite on **8.3,
  8.4 and 8.5**, plus a `--prefer-lowest` build, and `release.yml` re-tests the
  tagged commit on all three before publishing.

  No source changes were required. The code was audited against every 8.5
  BC-break: no `(boolean)`/`(integer)`/`(double)`/`(binary)` casts, no backtick
  operator (`shell_exec` is called as a function, which is unaffected), no
  `__sleep()`/`__wakeup()`, no `null` array offsets, and every `[]` destructure
  operates on a value already proven to be an array.

### Fixed

- `ViteManifest::prodTags()` emitted the stylesheet `<link>` twice. A JS entry
  lists the CSS it imports, and that same file is conventionally passed to
  `@vite()` explicitly as well, so the standard
  `@vite(['app.js', 'app.css'])` produced a duplicate tag.
- `release.yml` rejected correctly annotated tags. `actions/checkout` writes the
  tag into the runner's local refs as a plain commit pointer, so
  `git cat-file -t` reported `commit`; the check now asks the GitHub API for the
  real object type.

### Changed

- `phpunit/phpunit` widened to `^11.5 || ^12.0 || ^13.0`: PHPUnit 11 predates
  PHP 8.5, and 12+ is the line actively tested against it.
- `phpstan/phpstan` widened to `^1.10 || ^2.0`.

## [0.9.0] - 2026-08-08

> **Minor bump, not a patch.** Several fixes below change documented behaviour
>: `Request::header()` now returns real values where it previously returned
> the default, the container throws instead of injecting `null`, `app()` throws
> when the application is not bootstrapped, and `Validator::validated()` no
> longer includes unsubmitted fields. Below 1.0 Composer treats the minor
> number as the compatibility boundary, so `^0.8.0` will not pick this up.

### Added

- Middleware groups are now expanded by the pipeline, so `Route::middleware('web')`
  works. Groups can be registered at runtime with `Pipeline::addGroup()`.
- `405 Method Not Allowed` with an `Allow` header, instead of a `404` for a
  route that exists under a different verb.
- Route parameter constraints: `$route->where('id', '\d+')`.
- `Application::envBool()` for reading boolean environment variables.
- `Request::hasHeader()`, `Request::headers()`, `Response::forgetCookie()`,
  `Response::getCookies()`, `Schema::reset()`, `Container::forgetInstance()`,
  `ConnectionPool::setConnection()`/`reset()`/`resetInstance()`.
- `QueryBuilder::orderByRaw()`, `avg()`, `chunk()`, `orWhereNull()`,
  `orWhereNotNull()`, and `rightJoin()`.
- CSRF exemptions via `session.csrf_except`, with wildcard support, plus
  `X-XSRF-TOKEN` header support.
- `Async\ParallelException`, aggregating failures from `Parallel::run()`.

### Fixed

- **Attribute routing** (`#[Route]`, `#[Prefix]`, `#[Middleware]`) works at all.
  Every attribute class lived in a single file, so PSR-4 could never load them.
- **Optional route parameters** (`/users/{id?}`) match the bare path.
- **Soft-deleting models** are queryable: the delete filter produced invalid
  SQL as soon as a `where()` was added.
- **`where('column', null)`** produces `IS NULL` instead of a comparison that
  can never be true.
- **Header lookups**: every multi-word header (`Content-Type`,
  `X-Requested-With`, `X-CSRF-TOKEN`) silently returned the default, which
  disabled `isAjax()`, `isJson()` and header-based CSRF.
- **`env()` with falsy values**: `APP_DEBUG=0` no longer behaves as unset.
- **The `integer` validation rule accepts `0`**, and `min`/`max` compare the
  value rather than its string length for numeric fields.
- **`unique`/`exists` no longer pass silently** when the database is
  unreachable or the table name is wrong.
- **Flash data survives to the view**: it was being aged three times per
  request, wiping the bag before anything could read it.
- **Migrations with a class/filename mismatch fail loudly** instead of being
  skipped silently.
- **`Schema::` follows the current connection** rather than the first PDO it
  ever saw.
- **Responses can carry more than one cookie.**
- **`Route::prefix()->group()` no longer leaks** its prefix onto every route
  registered afterwards.
- `resource()` registers `PATCH` as well as `PUT`, and `/{id}/create` before
  `/{id}`.
- Circular container dependencies raise a clear exception instead of exhausting
  the stack.
- `Cannot redeclare class ContextualBindingBuilder`: it was declared twice.
- Service providers are registered and booted exactly once per class.
- `ThrottleMiddleware` accepts its `throttle:60` parameters (a `TypeError`
  crashed the entire built-in `api` middleware group).
- The `.env` parser handles quoted values, inline comments and `export`.
- Path helpers no longer mix `/` and `\` on Windows.

### Security

- **SQL injection in `orderBy()`**: both column and direction were
  interpolated verbatim. Also fixed in `join()` and the aggregate helpers.
- **Open redirect in every `back()`**: the client-supplied `Referer` was
  written straight into the `Location` header.
- **Session fixation on logout**: the session ID was never rotated.
- **`X-Forwarded-For` spoofing**: now only honoured from a configured
  `TRUSTED_PROXIES`.
- **`_method` spoofing from the query string**: restricted to POST bodies.
- **Unrestricted `unserialize()`** in the encrypter: now
  `allowed_classes => false`.
- **Unvalidated `APP_KEY` length**: openssl silently NUL-pads a short key,
  producing quietly weakened ciphertext.
- **Unescaped output on the debug error page.**
- Session cookies now apply `config/session.php` (`http_only`, `same_site`,
  `secure`, `lifetime`), which the framework previously ignored entirely.
- Array-valued `_token` and encrypter payload fields no longer raise an
  uncatchable `TypeError` (an unauthenticated 500 on every form endpoint).

### Changed

- Unresolvable container dependencies raise `RuntimeException` instead of
  injecting `null`.
- `app()` throws when the application has not been bootstrapped, rather than
  returning `null`.
- `Validator::validated()` returns only submitted fields.
- Unhandled exceptions are always logged, and never leak details in production.

## [0.8.0] - 2026-07-XX

Baseline for this changelog. Earlier releases are catalogued in the repository
history and, for the July 2026 audit, in [CHANGES.md](CHANGES.md).

[Unreleased]: https://github.com/libxa-framework/libxa/compare/v0.10.2...HEAD
[0.10.2]: https://github.com/libxa-framework/libxa/compare/v0.10.1...v0.10.2
[0.10.1]: https://github.com/libxa-framework/libxa/compare/v0.10.0...v0.10.1
[0.10.0]: https://github.com/libxa-framework/libxa/compare/v0.9.0...v0.10.0
[0.9.0]: https://github.com/libxa-framework/libxa/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/libxa-framework/libxa/releases/tag/v0.8.0
