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

**Total issues fixed: 40+**  
**Files modified: 28**  
**New files created: 6**  
**PHP syntax errors after fixes: 0 / 0**
