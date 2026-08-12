# LibxaFrame: Production Fixes Changelog

## Framework Core (`libxa-main`)

### 🔴 Critical Bug Fixes (Fatal Crashes)

| File | Issue | Fix |
|------|-------|-----|
| `Http/Request.php` | Missing `isMethodSafe()`: called by `CsrfMiddleware` on every POST/PUT/DELETE, causing fatal `Call to undefined method` | Added method returning `true` for GET/HEAD/OPTIONS |
| `Session/Session.php` | Missing `invalidate()`, `regenerateToken()`, `pull()`, `has()`: `LoginController::logout()` calls all of them | Implemented all four methods |
| `Atlas/Migrations/Migrator.php` | `fresh()` used SQLite-only `sqlite_master` query: crashes on MySQL/PostgreSQL | Driver-aware table dropping for SQLite, MySQL, PostgreSQL |
| `Validation/ValidationException.php` | Class completely missing: `Request::validate()` and `HttpKernel` both throw it | Created with `toResponse()` method for JSON API support |
| `Container/ContextualBindingBuilder.php` | Class completely missing: `Container::when()` returns it | Created with fluent `needs()`/`give()` API |
| `Atlas/Connection/ConnectionPool.php` | Duplicate `resolveFromEnv()` method body (second definition shadowed first, causing parse ambiguity) | Removed the duplicate stub |
| `Console/Commands/KeyGenerateCommand.php` | Used `$this->Libxa` property name instead of `$this->app`: `$this->Libxa->basePath()` throws `Undefined property` | Fixed property name to `$this->app` |
| `Console/Application.php` | Used `$this->Libxa` everywhere instead of `$this->app` | Fixed all references |

### 🟠 Major Functional Issues

| File | Issue | Fix |
|------|-------|-----|
| `Foundation/HttpKernel.php` | Referenced three non-existent middleware classes: `ShareErrorsMiddleware`, `ThrottleMiddleware`, `EmailVerifiedMiddleware` | Created all three middleware classes |
| `Foundation/HttpKernel.php` | Global middleware stack was declared but never piped through the request | Fixed `sendThroughPipeline()` to run global middleware before routing |
| `Providers/DatabaseServiceProvider.php` | Bound `ConnectionPool` singleton but never called `$pool->configure($connections)`: DB config was ignored | Added `$pool->configure($connections)` call with resolved default driver |
| `Auth/AuthManager.php` | Driver resolution used `ucfirst($driver)` but config value `'libxasecure'` didn't match `createLibxasecureDriver` | Normalised driver name with `str_replace('_', '', ucwords(strtolower(...), '_'))` |
| `Auth/AuthManager.php` | `createUserProvider()` never passed model class to `DBUserProvider`: always used `stdClass` for all auth lookups | Added model class extraction from provider config |
| `Auth/DBUserProvider.php` | Could not use Atlas Model classes for auth, only raw `stdClass` | Added Atlas Model-aware lookup path with `Authenticatable` proxy for plain objects |
| `Atlas/Schema/SchemaBuilder.php` | `table()` (ALTER TABLE) called the Blueprint callback but never called `build()`: no SQL executed | Added `$blueprint->build()` call |
| `Atlas/Schema/SchemaBuilder.php` | `hasTable()`/`hasColumn()` used SQLite-only queries | Driver-aware implementations for SQLite, MySQL, PostgreSQL |
| `Atlas/Schema/Blueprint.php` | No `$alter` parameter: `SchemaBuilder::table()` couldn't trigger ALTER TABLE | Added `$alter` flag; `build()` emits `ALTER TABLE ADD COLUMN` when set |
| `Multitenancy/Middleware/InitializeTenancy.php` | Always instantiated `TenancyManager` unconditionally: crashed entire app if tenancy misconfigured or disabled | Checks `TENANCY_ENABLED` env before initializing; wraps in try/catch in non-debug mode |
| `Router/Pipeline.php` | Resolved `HttpKernel` from container to get aliases: caused circular dependency at routing time | Added static alias map fallback; kernel lookup is now best-effort |
| `Providers/NovaServiceProvider.php` | Called `$this->app->make('router')` inside `register()`: router not yet available | Moved `registerRoutes()` call to `boot()` |
| `Multitenancy/TenantManager.php` | Called non-existent `$request->host()` method | Fixed to use `$request->server['HTTP_HOST']` |
| `Http/Middleware/SessionMiddleware.php` | Started session but never aged flash data: `@if(session('error'))` always returned empty in views | Added `$session->ageFlashData()` call before passing to next middleware |
| `Console/Commands/MigrateCommand.php` | Created `Migrator` before booting app: `ConnectionPool` couldn't resolve DB config | Added `$this->app->boot()` before `new Migrator()` |

### 🟡 Code Quality & Completeness

| File | Issue | Fix |
|------|-------|-----|
| `Validation/Validator.php` | `errors()` returned `array` but `ValidationException` expected `MessageBag` | Changed return type to `MessageBag` |
| `Validation/MessageBag.php` | Missing `toArray()`, `merge()`, `add()`, `count()` methods | Added all four |
| `Auth/LibxaSecure.php` | `findToken()` cast `null` to `(object)` returning empty `stdClass` instead of `null` | Removed cast; returns `null` when not found |
| `Blade/Compiler.php` | Missing `@method()`, `@session()`, `@old()`, `@json()`, `@stack()`, `@checked()`, `@selected()`, `@disabled()` directives | Implemented all in `compileMisc()` |
| `Blade/Compiler.php` | Regex strings for `@method`/`@session`/`@old` had mixed quote delimiters causing PHP parse error | Fixed all regex patterns to use consistent double-quote delimiters |
| `Support/helpers.php` | `session()` helper read `$_SESSION[$key]` directly: missed flash data set by `->with()` | Now checks `$_SESSION['_flash']['old'][$key]` first, then falls back to regular session |
| `Http/Request.php` | Fully rewritten with complete API: `boolean()`, `integer()`, `string()`, `only()`, `except()`, `filled()`, `missing()`, `bearerToken()`, `expectsJson()`, `isJson()`, `isSecure()`, `validate()`, `session()` | Added all missing methods |

### ✨ New Files Created

- `Http/Middleware/ShareErrorsMiddleware.php`: ages flash data before view rendering
- `Http/Middleware/ThrottleMiddleware.php`: rate limiting (60 req/min per IP, configurable)
- `Http/Middleware/EmailVerifiedMiddleware.php`: enforces `email_verified_at` presence
- `Container/ContextualBindingBuilder.php`: fluent contextual binding API
- `Validation/ValidationException.php`: throws on failed validation with JSON response support
- `Blade/BladeStack.php`: runtime support for `@push`/`@stack` directive pairs

---

## Starter App (`LibxaStack`)

### 🔴 Critical Bug Fixes

| File | Issue | Fix |
|------|-------|-----|
| `routes/api.php` | `use Libx\Billing\Http\Controllers\BillingController`: wrong namespace prefix `Libx\` (not `App\`) and class doesn't exist; route outside group caused parse error | Removed broken import; rewrote as clean public + auth-guarded API |
| `database/migrations/` | Two migration files both declared `class CreatePersonalAccessTokensTable`: PHP throws `class already declared` when both loaded | Removed the older duplicate (2026-04-07); kept the newer one with `expires_at` |

### 🟠 Major Functional Issues

| File | Issue | Fix |
|------|-------|-----|
| `App/Models/User.php` | Didn't extend `Atlas\Model`: no ORM integration (`save()`, `find()`, `create()` all missing) | Rewrote to extend `Model`, implement `Authenticatable`, add `setPasswordAttribute` mutator for auto-hashing |
| `config/auth.php` | Provider config had no `model` key: `DBUserProvider` always used `stdClass` for user lookups | Added `'model' => \App\Models\User::class` to users provider |
| `routes/web.php` | Only had the welcome route: auth routes for `/login`, `/register`, `/logout`, `/home` were absent | Added all auth routes with correct controller bindings |
| `Http/Controllers/Auth/LoginController.php` | `logout()` called `$session->invalidate()` and `$session->regenerateToken()` which didn't exist in `Session` class | Updated to use the now-fixed `Session` methods |
| `Http/Controllers/Auth/RegisterController.php` | Used `DB::table()->insertGetId()` directly, bypassing `User` model, password hashing, and model events | Rewrote to use `User::create()` |

### 🟡 Code Quality

| File | Issue | Fix |
|------|-------|-----|
| `.env.example` | `ATLAS_AI_ENABLED` declared three times | Deduplicated; cleaned up entire file |
| `resources/views/welcome.blade.php` | Debug artifact `"sss11 Welcome"` in view text | Fixed to `"Welcome"` |

---

## Summary

---

## View Layer Stability Pass: Blade-X Engine (July 2026)

Scope: `src/Blade/Compiler.php`, `src/Blade/BladeEngine.php`, `src/Blade/BladeStack.php`,
new tests in `tests/Feature/BladeCompilerTest.php`. This was a from-scratch audit of the
template engine specifically, since it's the piece every request touches.

### 🔴 Confirmed pre-existing crash (not introduced by this pass)

| Issue | Impact | Fix |
|---|---|---|
| `LibxaStack-main/src/resources/views/layouts/app.blade.php` uses `@hasSection('no-footer')`, but the compiler never implemented that directive | The starter kit's **own default layout**: the one every page extends: produced a fatal PHP parse error in its compiled cache file. Verified against the original, unmodified compiler. | Implemented `@hasSection` / `@endHasSection` and `@sectionMissing` / `@endSectionMissing` |

### 🔴 Stability bugs found and fixed in the engine itself

| Issue | Impact | Fix |
|---|---|---|
| `evaluateView()` only called `ob_end_clean()` once on exception | A section/component that throws mid-buffer leaks an output-buffer level. Harmless per-request under php-fpm, but **corrupts every subsequent request** on the framework's persistent Workerman/reactive server (`src/Reactive/WsServer.php`), since PHP's ob stack is process-global. Reproduced and verified with a standalone test that measures `ob_get_level()` before/after. | Track the ob baseline and unwind every level opened during the render, in a `finally`-guarded loop |
| Cache file written via a single `file_put_contents()` | Concurrent first-requests for the same cold view can interleave writes → a half-written PHP file gets `include()`'d → sporadic fatal parse errors in production | Write to a unique temp file, then `rename()` (atomic on POSIX) |
| `@if`/`@foreach`/`@while`/etc. used a fixed-one-level-deep regex for balanced parens | Two or more levels of nested parens (e.g. `@if(in_array($x, [f(1,2)]))`) truncated the condition and produced broken/incorrect PHP. Reproduced: original compiler threw a parse error on this exact input. | Replaced with a string-aware, arbitrary-depth paren scanner (`compileDirective()` / `findMatchingParen()`) that also correctly ignores parens inside quoted strings |
| View/component names were interpolated raw into generated PHP string literals | A quote character in a view name (or in the raw text near `@include(...)`) could produce a fatal parse error in the compiled cache file | All literal names now go through `addslashes()` before being embedded |
| `$__sections` was only initialized inside the `@extends` branch | A view using `@section(...)/@endsection` without `@extends` (a normal reusable-partial pattern) hit an undefined-variable warning | Every compiled template now starts with `$__sections = $__sections ?? []` |
| No recursion guard on `@include`/`@extends` chains | A circular include (`a` includes `b` includes `a`) crashed the whole PHP worker with a stack overflow instead of a catchable error | Added a depth counter (default max 64) that throws a clear `RuntimeException` |
| `@push`/`@endpush`/`@prepend` were documented (`BladeStack` class, doc comments) but never wired into the compiler | Using `@push` in a view silently printed the literal text `@push('name')` instead of doing anything | Implemented `@push`, `@endpush`, `@prepend`, `@endprepend` |
| `BladeStack`'s static stacks were never flushed | On the persistent reactive server, content pushed by one request could leak into another unrelated request's `@stack()` output forever | `BladeEngine` now calls `BladeStack::flush()` once at the start of every fresh (non-nested) render |
| No `@verbatim`/`@endverbatim` | The framework ships React/Vue/Svelte frontend adapters, both of which use `{{ }}` in their own templates, with no way to protect that syntax from Blade's compiler, it always got mangled | Added `@verbatim`/`@endverbatim` |
| Namespaced view resolution (`admin::users.index`) silently fell through to a broken generic path when the namespace was unregistered, producing a confusing error | Debuggability under `admin::` module views | Explicit "namespace not registered" exception; namespaces can now also register multiple fallback paths (`addNamespace()` accepts an array, matching Laravel's package/module view-overriding behavior) |
| Cache invalidation used `filemtime() >` only | A source edit in the same second as the previous compile could be served stale | Changed to `>=` and cache write is now all-or-nothing (see atomic write fix above), so a stale *and* corrupt cache can no longer coexist |
| Missing `@unless`, `@isset`/`@endisset`, `@includeIf`, `@includeWhen`, `@each`, `@slot`/`@endslot`, `@show`, `@readonly`, `@required` | Common Blade directives Laravel ships that this engine was missing | Implemented all of the above |

### Verification

All changes were verified by actually compiling and *executing* templates (not just
reading the code):
- Every `.blade.php` file shipped in both `libxa-main` and `LibxaStack-main` now compiles
  to lint-clean PHP (previously `layouts/app.blade.php` did not).
- A standalone reproduction proved the output-buffer leak on the original code and its
  absence on the new code (`ob_get_level()` before/after a mid-section exception).
- Adversarial inputs (nested parens 2–4 levels deep, quotes in view/include names,
  `@section` without `@extends`, circular `@include`, unregistered namespaces,
  `@push`/`@stack` across multiple pushes, `@verbatim` blocks) were compiled, linted,
  and executed end-to-end against the real `Compiler`/`BladeEngine` classes.
- New regression tests added at `tests/Feature/BladeCompilerTest.php` so these stay fixed.


---

# Stability Audit: August 2026

A second full pass over the framework core and the `LibxaStack` starter kit,
focused on crashes, silent wrong-behaviour, and security. Everything below was
reproduced against real code (a real container, a real SQLite database, real
requests through the HTTP kernel) before being fixed, and each item is locked in
by a regression test. Suite: **197 framework tests / 25 starter-kit tests**.

## 🔴 Fatal / process-killing

| Area | Issue | Fix |
|---|---|---|
| `Container/ContextGraph.php` | Declared a **second copy** of `ContextualBindingBuilder`, which already has its own file. The moment both files loaded: i.e. as soon as an app used `->when()`: PHP died with `Cannot redeclare class`. | Removed the duplicate; merged its `whenContext()` into the real class. |
| PSR-4 across `src/` | **12 files declared classes the autoloader could never find** (`Attributes/Route.php` held 6 attribute classes, `Support/Str.php` hid `StringableProxy`, `Atlas/QueryBuilder.php` hid `RawExpression`, `Blade/BladeEngine.php` hid `SharedData`, …). Referencing one gave `Class not found`; `#[Prefix]`/`#[Middleware]` attribute routing was entirely unusable. | Every class extracted into its own PSR-4 file. `tests/Feature/AutoloadingTest.php` now fails the build if this regresses. |
| `Container/Container.php` | Two classes depending on each other recursed until PHP exhausted the stack: the process died with no usable error. | Explicit circular-dependency detection naming the full resolution chain. |
| `Container/Container.php` | If a dependency threw, `buildStack` was never popped, corrupting contextual resolution for the rest of the process. | `try`/`finally` unwind. |
| `Http/Middleware/ThrottleMiddleware.php` | The pipeline parses `throttle:60` into the **string** `"60"`; with `strict_types=1` on both sides that is an uncatchable `TypeError`. The built-in `api` middleware group crashed on its first request. | Parameters accept `int\|string` and are cast. |
| `Router/Pipeline.php` | `Route::middleware('web')` asked the container to build a class literally named `web` → `Target class [web] does not exist`. Middleware **groups were never expanded**. | Groups are expanded (and de-duplicated) before the pipeline runs. |
| `Security/Encrypter.php` | A payload with array fields (`?p[iv][]=x`) reached `base64_decode()`/`hash_equals()` and raised a `TypeError`: an unauthenticated 500 on any endpoint decrypting user input. | Every payload field is type-checked before use. |
| `Http/Middleware/CsrfMiddleware.php` | `_token[]=x` reached `hash_equals()` with an array: same unauthenticated 500, on **every form endpoint**. | Non-string tokens are rejected as a clean 419. |
| `Foundation/HttpKernel.php` | If the exception handler itself threw (e.g. `back()` with no session), the process died with a blank 500 and the original exception was lost. | Nested guard that reports both exceptions. |
| `Async/Parallel.php` | A fiber left in a non-resumable state made the scheduler loop spin forever until the PHP time limit killed the request. | Explicit state handling; per-task exception capture via `ParallelException`. |

## 🟠 Silent wrong behaviour

| Area | Issue | Fix |
|---|---|---|
| `Http/Request.php` | `capture()` stored headers as `CONTENT-TYPE` while `header()` looked up `CONTENT_TYPE`. **Every multi-word header lookup silently returned the default**, disabling `isAjax()`, `isJson()` and the `X-CSRF-TOKEN` branch of CSRF. | One canonical normalisation (`normalizeHeaderName()`) used by both. |
| `Foundation/Application.php` | `env()` was `a ?? b ?: c ?? d`, which PHP groups as `(a ?? b) ?: (c ?? d)`, so **any falsy value fell through to the default** and `APP_DEBUG=0` behaved like an unset `APP_DEBUG`. | Presence-based lookup per source, plus `envBool()` for the `=== 'true'` comparisons scattered around the framework. |
| `Atlas/QueryBuilder.php` | The soft-delete filter was emitted while the "first condition" flag was still set, so the first user `where()` got no `AND`: producing `WHERE deleted_at IS NULL "id" = ?`. **Any soft-deleting model with a where clause was unqueryable.** | Correct boolean joining. |
| `Atlas/QueryBuilder.php` | `where('col', null)` emitted `col = ?` bound to `NULL`, which is never true in SQL, so the row could never be found; `where('c','>',null)` silently became `c = '>'`. | Argument count decides; null becomes `IS NULL` / `IS NOT NULL`. |
| `Atlas/Schema/Schema.php` | The `SchemaBuilder` was memoised in a static nothing invalidated, capturing the **first PDO it ever saw**. After a reconnect/tenant switch, every `Schema::` call wrote DDL to the *previous* database. | Rebuilt whenever the underlying handle changes; `Schema::reset()` added. |
| `Atlas/Migrations/Migrator.php` | A migration whose class name didn't match its filename was **skipped in silence**: exit code 0, no warning. The starter kit shipped exactly such a file, so `personal_access_tokens.refresh_token` never existed. | The declared class is read from the file; a file with no usable migration class is a hard error. |
| `Session/Session.php` | `ageFlashData()` ran from three call sites per request. The second run moved the now-empty bucket over the readable one, **deleting flash messages before any view saw them**: the reason `back()->with('error', …)` did nothing. | Idempotent per request; duplicate call sites removed. |
| `Validation/Validator.php` | `! filter_var($v, FILTER_VALIDATE_INT)`: `filter_var('0')` is `int(0)`, which is falsy, so **`0` failed the `integer` rule**. | Compare against `false`. |
| `Validation/Validator.php` | `min`/`max` measured string *length* even for numeric fields, so `integer\|min:18` compared `mb_strlen('20') === 2` against 18 and always failed. | Numeric-aware sizing. |
| `Validation/Validator.php` | `unique`/`exists` swallowed every `Throwable`, so a DB outage or a typo'd table made `unique` silently **pass**: the one case where it must not. | Only a genuinely absent connection is tolerated; identifiers validated and quoted per driver. |
| `Validation/Validator.php` | `validated()` returned `field => null` for absent optional fields, so a mass update wrote nulls over real column values. | Only submitted fields are returned. |
| `Router/Router.php` | `prefix()`/`middleware()`/`name()` pushed onto the group stack; `group()` popped one frame, so `Route::prefix('api')->group(...)` **permanently prefixed every route registered afterwards**. | Staged separately and consumed by `group()`, which pops in a `finally`. |
| `Router/Route.php` | `/users/{id?}` compiled to `/users/(?P<id>[^/]+)?`: the slash was mandatory, so **optional parameters could never match** the bare path. A literal `.` in a URI matched any character. | Slash moved inside the optional group; literals `preg_quote`'d; `where()` constraints added. |
| `Router/Route.php` | Matched parameters lived only on the shared Route object, so under a persistent worker request N+1 could read request N's parameters. | Parameters are bound to the Request. |
| `Router/Router.php` | A wrong HTTP verb returned 404, indistinguishable from a typo'd URL. | 405 + `Allow` header. |
| `Router/Router.php` | `resource()` registered only `PUT` for updates, so every `PATCH` 404'd; `/{id}` was registered before `/create`. | Both verbs; correct ordering. |
| `Foundation/Application.php` | A provider reachable from several discovery paths was registered once per path, duplicating its routes, listeners and commands. | Registration and booting are idempotent per class. |
| `Foundation/Application.php` | The `.env` parser ran `trim($v, " \"'")` over the raw value: quoted values with spaces/`#` were mangled and inline comments were never stripped. | Proper quote/comment/`export` handling. |
| `Foundation/Application.php` | Path helpers hard-coded `/` while `basePath()` used `DIRECTORY_SEPARATOR`, producing `C:\app\src/app`. | Consistent joining. |
| `Http/Response.php` | Every cookie was written to `$headers['Set-Cookie']`, so each call overwrote the last: **a response could only ever carry one cookie**. | Cookies kept in their own list. |
| `Support/helpers.php` | `app()` returned `null` when the app wasn't bootstrapped, so failures surfaced far away as `… on null`. | Throws where the mistake actually is. |
| `LibxaStack/src/bootstrap/*.php` | Four bootstrapped files began with a **UTF-8 BOM**, emitting output before `header()` on every request. | Stripped; a test now guards it. |

## 🛡️ Security

| Area | Issue | Fix |
|---|---|---|
| `Http/Request.php` | `X-Forwarded-For` was trusted unconditionally, so anyone could reset their own rate-limit bucket or forge audit IPs with one header. | Only honoured from a configured `TRUSTED_PROXIES`. |
| `Http/Request.php` | `_method` was read from the query string, so `<img src="/x?_method=DELETE">` could reach a destructive route on a plain navigation. | POST bodies only. |
| `Http/Response.php` / `back()` | The client-supplied `Referer` was echoed straight into `Location`: **every "redirect back" was an open redirect**. | Reduced to a same-origin target (`safeReferer()`), including protocol-relative `//evil.com`. |
| `Atlas/QueryBuilder.php` | `orderBy()` interpolated both column and direction verbatim: a direct SQL injection in the most common way it gets called (`orderBy($request->input('sort'), …)`). Same for `join()` and the aggregate helpers. | Identifiers quoted, direction/type/operator whitelisted; `orderByRaw()` for deliberate raw SQL. |
| `Security/Encrypter.php` | Key length was never validated; openssl silently NUL-pads a short key, so a truncated `APP_KEY` produced quietly weakened ciphertext that still round-tripped in testing. | Length validated per cipher; `base64:` keys decoded; clear error pointing at `key:generate`. |
| `Security/Encrypter.php` | `decrypt()` called `unserialize()` with no restrictions: object injection if a key ever leaked. | `allowed_classes => false`. |
| `Session/Session.php` | `invalidate()` called `session_destroy()` then checked for `PHP_SESSION_NONE`, which never matches in the same request: **the session ID was never rotated on logout** (session fixation). | `session_regenerate_id(true)`. |
| `Session/Session.php` | `config/session.php` shipped `http_only`, `same_site`, `secure` and `lifetime` settings the class **never read**; every app ran on php.ini defaults with no `SameSite`. | Config is applied to the session cookie. |
| `Foundation/HttpKernel.php` | The debug error page interpolated the exception message, class and file into HTML unescaped. | All escaped. |
| `Foundation/HttpKernel.php` | Unexpected exceptions were swallowed silently in production. | Always logged, never leaked to the client. |
| `Http/Middleware/CsrfMiddleware.php` | No way to exempt a URI, so webhook receivers forced apps to delete the middleware wholesale. | `session.csrf_except` with wildcard support; `X-XSRF-TOKEN` accepted. |

## 🧪 Testing

- `tests/TestCase.php`: boots a **real** `Application` against a throwaway skeleton.
  The previous tests mocked `Application` and tried to stub the *static* `env()`,
  which cannot work; every one of them errored.
- New suites: `Unit/{Container,Route,Request,Response,Session,Validator,Encrypter,QueryBuilder,Schema}Test`
  and `Feature/{Application,Router,HttpKernel,CsrfMiddleware,Autoloading}Test`.
- `QueryBuilderTest`/`SchemaTest` run against real in-memory SQLite, so SQL that does
  not parse fails here rather than in production.
- The starter kit gains `phpunit.xml` (its CI ran `pest` with **no configuration at all**,
  so it could never have run a test), a `Tests\TestCase` that migrates a per-test
  database and drives the real kernel, and end-to-end coverage of
  register → login → protected page → logout.

## 🔗 Framework ↔ starter-kit wiring

`LibxaStack` shipped a **mirrored copy** of the framework in
`vendor/libxa/framework`. Nothing kept it in step with the real source, and the
two had drifted in both directions: the vendored copy carried console commands
the source lacked, while the source carried bug fixes and whole directories the
copy lacked. The practical effect was that fixing a bug in the framework had no
effect on the application at all.

The path repository now uses `symlink: true`, so `vendor/libxa/framework` *is*
`../libxaframe` (a junction on Windows). Framework edits, including brand-new
classes, which the PSR-4 autoloader picks up with no `dump-autoload`: take
effect on the very next request, and the two copies can no longer disagree.

Two deliberate details in the starter kit's `composer.json`:

- the repository `url` is the glob `../libxaframe*`. A literal path that does
  not exist makes Composer **abort**, which would break `composer create-project`
  for anyone without the sibling checkout; a glob matching nothing is ignored,
  so resolution falls through to Packagist.
- the constraint is `dev-main || ^0.8.0`: the local checkout wins when present,
  and a published release is still installable when it is not.

`LibxaStack/tests/Feature/FrameworkLinkTest.php` fails the build if the link is
ever replaced by a copy, if the two trees diverge, or if either of the two
`composer.json` details above is undone. It skips when there is no sibling
checkout, which is the correct state for CI and production installs.
