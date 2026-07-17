# LibxaFrame — Production Fixes Changelog

## Framework Core (`libxa-main`)

### 🔴 Critical Bug Fixes (Fatal Crashes)

| File | Issue | Fix |
|------|-------|-----|
| `Http/Request.php` | Missing `isMethodSafe()` — called by `CsrfMiddleware` on every POST/PUT/DELETE, causing fatal `Call to undefined method` | Added method returning `true` for GET/HEAD/OPTIONS |
| `Session/Session.php` | Missing `invalidate()`, `regenerateToken()`, `pull()`, `has()` — `LoginController::logout()` calls all of them | Implemented all four methods |
| `Atlas/Migrations/Migrator.php` | `fresh()` used SQLite-only `sqlite_master` query — crashes on MySQL/PostgreSQL | Driver-aware table dropping for SQLite, MySQL, PostgreSQL |
| `Validation/ValidationException.php` | Class completely missing — `Request::validate()` and `HttpKernel` both throw it | Created with `toResponse()` method for JSON API support |
| `Container/ContextualBindingBuilder.php` | Class completely missing — `Container::when()` returns it | Created with fluent `needs()`/`give()` API |
| `Atlas/Connection/ConnectionPool.php` | Duplicate `resolveFromEnv()` method body (second definition shadowed first, causing parse ambiguity) | Removed the duplicate stub |
| `Console/Commands/KeyGenerateCommand.php` | Used `$this->Libxa` property name instead of `$this->app` — `$this->Libxa->basePath()` throws `Undefined property` | Fixed property name to `$this->app` |
| `Console/Application.php` | Used `$this->Libxa` everywhere instead of `$this->app` | Fixed all references |

### 🟠 Major Functional Issues

| File | Issue | Fix |
|------|-------|-----|
| `Foundation/HttpKernel.php` | Referenced three non-existent middleware classes: `ShareErrorsMiddleware`, `ThrottleMiddleware`, `EmailVerifiedMiddleware` | Created all three middleware classes |
| `Foundation/HttpKernel.php` | Global middleware stack was declared but never piped through the request | Fixed `sendThroughPipeline()` to run global middleware before routing |
| `Providers/DatabaseServiceProvider.php` | Bound `ConnectionPool` singleton but never called `$pool->configure($connections)` — DB config was ignored | Added `$pool->configure($connections)` call with resolved default driver |
| `Auth/AuthManager.php` | Driver resolution used `ucfirst($driver)` but config value `'libxasecure'` didn't match `createLibxasecureDriver` | Normalised driver name with `str_replace('_', '', ucwords(strtolower(...), '_'))` |
| `Auth/AuthManager.php` | `createUserProvider()` never passed model class to `DBUserProvider` — always used `stdClass` for all auth lookups | Added model class extraction from provider config |
| `Auth/DBUserProvider.php` | Could not use Atlas Model classes for auth, only raw `stdClass` | Added Atlas Model-aware lookup path with `Authenticatable` proxy for plain objects |
| `Atlas/Schema/SchemaBuilder.php` | `table()` (ALTER TABLE) called the Blueprint callback but never called `build()` — no SQL executed | Added `$blueprint->build()` call |
| `Atlas/Schema/SchemaBuilder.php` | `hasTable()`/`hasColumn()` used SQLite-only queries | Driver-aware implementations for SQLite, MySQL, PostgreSQL |
| `Atlas/Schema/Blueprint.php` | No `$alter` parameter — `SchemaBuilder::table()` couldn't trigger ALTER TABLE | Added `$alter` flag; `build()` emits `ALTER TABLE ADD COLUMN` when set |
| `Multitenancy/Middleware/InitializeTenancy.php` | Always instantiated `TenancyManager` unconditionally — crashed entire app if tenancy misconfigured or disabled | Checks `TENANCY_ENABLED` env before initializing; wraps in try/catch in non-debug mode |
| `Router/Pipeline.php` | Resolved `HttpKernel` from container to get aliases — caused circular dependency at routing time | Added static alias map fallback; kernel lookup is now best-effort |
| `Providers/NovaServiceProvider.php` | Called `$this->app->make('router')` inside `register()` — router not yet available | Moved `registerRoutes()` call to `boot()` |
| `Multitenancy/TenantManager.php` | Called non-existent `$request->host()` method | Fixed to use `$request->server['HTTP_HOST']` |
| `Http/Middleware/SessionMiddleware.php` | Started session but never aged flash data — `@if(session('error'))` always returned empty in views | Added `$session->ageFlashData()` call before passing to next middleware |
| `Console/Commands/MigrateCommand.php` | Created `Migrator` before booting app — `ConnectionPool` couldn't resolve DB config | Added `$this->app->boot()` before `new Migrator()` |

### 🟡 Code Quality & Completeness

| File | Issue | Fix |
|------|-------|-----|
| `Validation/Validator.php` | `errors()` returned `array` but `ValidationException` expected `MessageBag` | Changed return type to `MessageBag` |
| `Validation/MessageBag.php` | Missing `toArray()`, `merge()`, `add()`, `count()` methods | Added all four |
| `Auth/LibxaSecure.php` | `findToken()` cast `null` to `(object)` returning empty `stdClass` instead of `null` | Removed cast; returns `null` when not found |
| `Blade/Compiler.php` | Missing `@method()`, `@session()`, `@old()`, `@json()`, `@stack()`, `@checked()`, `@selected()`, `@disabled()` directives | Implemented all in `compileMisc()` |
| `Blade/Compiler.php` | Regex strings for `@method`/`@session`/`@old` had mixed quote delimiters causing PHP parse error | Fixed all regex patterns to use consistent double-quote delimiters |
| `Support/helpers.php` | `session()` helper read `$_SESSION[$key]` directly — missed flash data set by `->with()` | Now checks `$_SESSION['_flash']['old'][$key]` first, then falls back to regular session |
| `Http/Request.php` | Fully rewritten with complete API: `boolean()`, `integer()`, `string()`, `only()`, `except()`, `filled()`, `missing()`, `bearerToken()`, `expectsJson()`, `isJson()`, `isSecure()`, `validate()`, `session()` | Added all missing methods |

### ✨ New Files Created

- `Http/Middleware/ShareErrorsMiddleware.php` — ages flash data before view rendering
- `Http/Middleware/ThrottleMiddleware.php` — rate limiting (60 req/min per IP, configurable)
- `Http/Middleware/EmailVerifiedMiddleware.php` — enforces `email_verified_at` presence
- `Container/ContextualBindingBuilder.php` — fluent contextual binding API
- `Validation/ValidationException.php` — throws on failed validation with JSON response support
- `Blade/BladeStack.php` — runtime support for `@push`/`@stack` directive pairs

---

## Starter App (`LibxaStack`)

### 🔴 Critical Bug Fixes

| File | Issue | Fix |
|------|-------|-----|
| `routes/api.php` | `use Libx\Billing\Http\Controllers\BillingController` — wrong namespace prefix `Libx\` (not `App\`) and class doesn't exist; route outside group caused parse error | Removed broken import; rewrote as clean public + auth-guarded API |
| `database/migrations/` | Two migration files both declared `class CreatePersonalAccessTokensTable` — PHP throws `class already declared` when both loaded | Removed the older duplicate (2026-04-07); kept the newer one with `expires_at` |

### 🟠 Major Functional Issues

| File | Issue | Fix |
|------|-------|-----|
| `App/Models/User.php` | Didn't extend `Atlas\Model` — no ORM integration (`save()`, `find()`, `create()` all missing) | Rewrote to extend `Model`, implement `Authenticatable`, add `setPasswordAttribute` mutator for auto-hashing |
| `config/auth.php` | Provider config had no `model` key — `DBUserProvider` always used `stdClass` for user lookups | Added `'model' => \App\Models\User::class` to users provider |
| `routes/web.php` | Only had the welcome route — auth routes for `/login`, `/register`, `/logout`, `/home` were absent | Added all auth routes with correct controller bindings |
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

## View Layer Stability Pass — Blade-X Engine (July 2026)

Scope: `src/Blade/Compiler.php`, `src/Blade/BladeEngine.php`, `src/Blade/BladeStack.php`,
new tests in `tests/Feature/BladeCompilerTest.php`. This was a from-scratch audit of the
template engine specifically, since it's the piece every request touches.

### 🔴 Confirmed pre-existing crash (not introduced by this pass)

| Issue | Impact | Fix |
|---|---|---|
| `LibxaStack-main/src/resources/views/layouts/app.blade.php` uses `@hasSection('no-footer')`, but the compiler never implemented that directive | The starter kit's **own default layout** — the one every page extends — produced a fatal PHP parse error in its compiled cache file. Verified against the original, unmodified compiler. | Implemented `@hasSection` / `@endHasSection` and `@sectionMissing` / `@endSectionMissing` |

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
| No `@verbatim`/`@endverbatim` | The framework ships React/Vue/Svelte frontend adapters, both of which use `{{ }}` in their own templates — with no way to protect that syntax from Blade's compiler, it always got mangled | Added `@verbatim`/`@endverbatim` |
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

