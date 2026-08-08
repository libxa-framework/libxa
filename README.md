# LibxaFrame

The modern, elegant, and lightning-fast PHP framework for the next generation of web applications. Built around developer happiness, performance, and scalability.

## Features

- 🚀 **Blazing Fast** - Optimized for performance with minimal overhead
- 🧠 **AI Integration** - First-class AI/LLM integration out of the box
- ⚡ **Async/Fibers** - Native PHP 8.1+ Fiber concurrency without extensions
- 🔌 **WebSockets** - Real-time event-driven WebSocket ecosystem
- 🏗️ **Modular Architecture** - Package-based modular system
- 🎨 **Elegant Syntax** - Clean, expressive code following modern PHP standards
- 🛡️ **Security** - Built-in security features including LibxaSecure
- 📦 **Package System** - First-party and third-party package support
- 🗄️ **ORM & Query Builder** - Powerful Atlas ORM with AI-powered queries
- 🔄 **Multi-Tenancy** - Zero-config multi-tenancy support
- 📁 **File Storage** - Consistent filesystem API
- 🧵 **Queue System** - Asynchronous job processing
- 🌐 **HTTP Client** - Connection pooling for parallel requests
- 📝 **Helpers** - Extensive utility functions

## Installation

```bash
composer create-project libxa/framework your-app
cd your-app
php libxa serve
```

## Quick Start

### 1. Configuration

Set your environment variables in `.env`:

```env
APP_NAME=YourApp
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=database.sqlite

OPENAI_API_KEY=your-api-key
```

### 2. Database Setup

```bash
php libxa migrate
```

### 3. Run Development Server

```bash
php libxa serve
```

Visit `http://localhost:8000` to see your application.

## Framework Lifecycle

The LibxaFrame request lifecycle follows this flow:

```
1. HTTP Request → Public/index.php
2. HttpKernel receives request
3. Middleware Pipeline executes
4. Router matches route
5. Controller/Closure executes
6. Response generated
7. Middleware Pipeline processes response
8. Response sent to client
```

### Detailed Lifecycle

1. **Entry Point** - `public/index.php` boots the application
2. **Application Boot** - Service providers are registered and booted
3. **HTTP Kernel** - Handles the request through middleware
4. **Middleware Pipeline** - Global and route-specific middleware execute
5. **Router Dispatch** - Matches URL to controller action
6. **Controller Execution** - Business logic runs
7. **Response Generation** - Response object created
8. **Termination** - Middleware cleanup and final actions

## Core Features

### AI Integration

LibxaFrame provides first-class AI integration with OpenAI and compatible services:

```php
// Generate text
$response = AI::text("Write a short poem about coding.");

// Classification
$category = AI::classify("I love this framework!", ['positive', 'negative', 'neutral']);

// Embeddings
$vector = AI::embed("LibxaFrame is awesome.");

// Data extraction
$data = AI::extract("John Doe is a software engineer.", [
    'name' => 'string',
    'occupation' => 'string'
]);

// Natural language database queries
$result = DB::ask("total revenue from users in Paris last month");
```

**Configuration:**
```env
OPENAI_API_KEY=your-api-key
AI_BASE_URL=https://api.openai.com/v1
ATLAS_AI_ENABLED=true
ATLAS_AI_PROVIDER=openai
ATLAS_AI_MODEL=gpt-4o-mini
```

### Async & Fibers

True asynchronous behavior using native PHP 8.1+ Fibers:

```php
use Libxa\Async\Parallel;

// Run tasks concurrently
[$users, $orders, $ads] = Parallel::run([
    'users'  => fn() => User::all(),
    'orders' => fn() => Order::recent(),
    'ads'    => fn() => Http::get('https://api.ads.com/serve'),
]);

// HTTP connection pooling
use Libxa\Http\Client;

$responses = Client::pool([
    fn() => (new Client())->get('https://api1.com/data'),
    fn() => (new Client())->get('https://api2.com/data'),
    fn() => (new Client())->get('https://api3.com/data'),
]);

// Fiber queue workers
Queue::fiber()
    ->batch($massiveArrayOfJobs)
    ->maxConcurrency(10)
    ->dispatch();
```

### WebSockets

Event-driven WebSocket ecosystem built on Workerman:

```bash
php libxa ws:serve
```

**WebSocket Controller:**
```php
use Libxa\WebSockets\WebSocketController;

class ChatController extends WebSocketController
{
    public function onMessage($connection, $data)
    {
        $this->broadcast($data);
    }
}
```

### Modular Package System

First-class package support with automatic discovery:

```bash
php libxa package:discover
php libxa vendor:publish --provider="Vendor\Package\ServiceProvider"
```

**Package Structure:**
```
packages/
└── my-package/
    ├── src/
    │   ├── MyServiceProvider.php
    │   ├── Routes/
    │   ├── Views/
    │   └── Database/Migrations/
    └── composer.json
```

### Atlas ORM & Query Builder

Powerful database abstraction with AI capabilities:

```php
// Query builder
$users = DB::table('users')
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->get();

// Model
$user = User::find(1);
$user->name = 'John';
$user->save();

// AI-powered queries
$result = DB::ask("find all users who signed up last week");
```

### Multi-Tenancy

Zero-config multi-tenancy support:

```env
TENANCY_ENABLED=true
TENANCY_DRIVER=subdomain
```

```php
// Automatic tenant resolution based on subdomain
tenant()->id; // Current tenant ID
tenant()->connection; // Tenant-specific database connection
```

### File Storage

Consistent filesystem API:

```php
// Store file
Storage::put('avatars/user1.jpg', $fileContents);

// Retrieve file
$contents = Storage::get('avatars/user1.jpg');

// Check existence
if (Storage::exists('avatars/user1.jpg')) {
    // ...
}

// Delete file
Storage::delete('avatars/user1.jpg');

// File URLs
$url = Storage::url('avatars/user1.jpg');
```

### Helpers & Utilities

Extensive helper functions:

```php
// String helpers
str('Hello World')->slug(); // hello-world
str()->limit('Long text...', 10); // Long te...

// Array helpers
collect([1, 2, 3])->avg(); // 2
collect([1, 2, 3])->sum(); // 6

// Path helpers
app_path();
storage_path();
public_path();

// Time helpers
now()->format('Y-m-d');
now()->addDays(7);
```

### Queue System

Asynchronous job processing:

```php
// Create a job
class SendEmail implements ShouldQueue
{
    public function handle()
    {
        Mail::to($this->user)->send(new WelcomeEmail());
    }
}

// Dispatch job
SendEmail::dispatch($user);

// Process queue
php libxa queue:work
```

### HTTP Client

Powerful HTTP client with connection pooling:

```php
use Libxa\Http\Client;

$client = new Client();
$response = $client->get('https://api.example.com/data');
$data = $response->json();

// POST request
$response = $client->post('https://api.example.com/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

## Routing

### Basic Routes

```php
$router->get('/', function () {
    return view('welcome');
});

$router->get('/users/{id}', function ($id) {
    return "User {$id}";
});
```

### Controller Routes

```php
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
```

### Route Groups

```php
$router->group(['prefix' => 'admin', 'middleware' => 'auth'], function ($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
});
```

### Resource Routes

```php
$router->resource('posts', PostController::class);
```

## Controllers

```php
<?php

namespace App\Http\Controllers;

use Libxa\Http\Request;
use Libxa\Http\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users'
        ]);

        User::create($validated);
        return redirect('/users');
    }
}
```

## Middleware

### Creating Middleware

```php
<?php

namespace App\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

class LogRequest
{
    public function handle(Request $request, callable $next): Response
    {
        Log::info("Request: {$request->url()}");
        return $next($request);
    }
}
```

### Registering Middleware

```php
// In routes
$router->group(['middleware' => 'auth'], function ($router) {
    // ...
});

// Or in HttpKernel
protected array $middleware = [
    LogRequest::class,
];
```

## Views & Blade

LibxaFrame uses the Blade templating engine:

```php
// Return view
return view('welcome', ['name' => 'John']);

// In view
<h1>Hello, {{ $name }}</h1>

@if($user)
    <p>Welcome back!</p>
@else
    <p>Please login</p>
@endif
```

### Layouts

```php
// layouts/app.blade.php
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title')</title>
</head>
<body>
    @yield('content')
</body>
</html>

// page.blade.php
@extends('layouts.app')

@section('title', 'My Page')

@section('content')
    <h1>My Content</h1>
@endsection
```

## Database

### Migrations

```bash
php libxa make:migration create_users_table
```

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamps();
});
```

### Models

```bash
php libxa make:model User
```

```php
<?php

namespace App\Models;

use Libxa\Atlas\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
    
    protected $hidden = ['password'];
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

## Console Commands

### Creating Commands

```bash
php libxa make:command SendEmails
```

```php
<?php

namespace App\Console\Commands;

use Libxa\Console\Command;

class SendEmails extends Command
{
    protected static $defaultName = 'emails:send';
    
    protected function configure(): void
    {
        $this->setDescription('Send pending emails');
    }
    
    protected function execute(): int
    {
        // Your logic
        return Command::SUCCESS;
    }
}
```

## Security

### LibxaSecure

Built-in security features:

```php
// Encryption
$encrypted = encrypt($data);
$decrypted = decrypt($encrypted);

// Hashing
$hashed = Hash::make('password');
if (Hash::check('password', $hashed)) {
    // Valid
}

// CSRF protection (automatic)
```

### Authentication

```php
// Login
Auth::attempt(['email' => $email, 'password' => $password]);

// Get current user
$user = Auth::user();

// Logout
Auth::logout();
```

## Deployment

### Production Build

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# Set environment
cp .env.example .env
php libxa key:generate

# Run migrations
php libxa migrate

# Build assets
npm install
npm run build
```

### Server Configuration

**Nginx:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/your-app/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Background Processes

**Supervisor for Queues:**
```ini
[program:libxa-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/your-app/libxa queue:work
autostart=true
autorestart=true
user=www-data
```

## Package Development

### Creating a Package

```bash
php libxa make:package MyPackage
```

This creates:
```
packages/my-package/
├── src/
│   ├── MyPackageServiceProvider.php
│   ├── Routes/
│   ├── Views/
│   └── Database/Migrations/
└── composer.json
```

### Package Discovery

```php
// In MyPackageServiceProvider
class MyPackageServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'mypackage');
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }
}
```

## Testing

```bash
php libxa test
```

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_basic_assertion(): void
    {
        $this->assertTrue(true);
    }
}
```

## Performance Optimization

### Caching

```php
// Cache value
Cache::put('key', 'value', 3600);

// Get cached value
$value = Cache::get('key');

// Remember pattern
$value = Cache::remember('key', 3600, function () {
    return DB::table('users')->get();
});
```

### Query Optimization

```php
// Eager loading
$users = User::with('posts')->get();

// Select specific columns
$users = User::select('id', 'name')->get();

// Chunking
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        // Process
    }
});
```

## Configuration

### Environment Variables

```env
APP_NAME=LibxaFrame
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_DRIVER=file
SESSION_DRIVER=file

OPENAI_API_KEY=your-api-key
```

### Configuration Files

Configuration files are located in `src/config/`:

```php
// config/app.php
return [
    'name' => env('APP_NAME', 'LibxaFrame'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
];
```

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) first, and
see [How this project works](#how-this-project-works) below for the branch and
release model. **Branch from `develop`, never from `main`.**

## License

LibxaFrame is open-sourced software licensed under the [MIT license](LICENSE).

## Support

- **Issues**: [GitHub Issues](https://github.com/libxa-framework/libxa/issues)
- **Questions**: [GitHub Discussions](https://github.com/libxa-framework/libxa/discussions)
- **Security**: [SECURITY.md](SECURITY.md) - never a public issue

## Acknowledgments

Built with ❤️ using PHP 8.3+

---

**LibxaFrame - The Modern PHP Framework**

---|---|
| [CONTRIBUTING.md](CONTRIBUTING.md) | Local setup, branch rules, commit convention, test policy |
| [docs/BRANCHING.md](docs/BRANCHING.md) | The branch model — `main` is what Packagist publishes |
| [docs/RELEASING.md](docs/RELEASING.md) | Release and hotfix runbook |
| [docs/REPOSITORY_SETUP.md](docs/REPOSITORY_SETUP.md) | One-time GitHub settings: branch protection, tag rules, Packagist webhook |
| [SECURITY.md](SECURITY.md) | Private vulnerability disclosure and supported versions |
| [CHANGELOG.md](CHANGELOG.md) | What changed in each release |
| [CHANGES.md](CHANGES.md) | Engineering write-up of the July and August 2026 stability audits |

Contributions branch from `develop`, never from `main`.

---

# How this project works

Everything below is the **operating model**: how the two repositories relate,
how a change travels from a developer's machine to Packagist, and what is
enforced automatically along the way. The reference documents
([BRANCHING](docs/BRANCHING.md), [RELEASING](docs/RELEASING.md),
[REPOSITORY_SETUP](docs/REPOSITORY_SETUP.md)) go deeper; this section is the
complete picture in one place.

## The two repositories

| Repository | Packagist | Type | What it is |
|---|---|---|---|
| [libxa-framework/libxa](https://github.com/libxa-framework/libxa) | `libxa/framework` | library | **This repo.** The framework itself. Installed into `vendor/`. |
| [libxa-framework/LibxaStack](https://github.com/libxa-framework/LibxaStack) | `libxa/libxa` | project | The starter kit. What `composer create-project` produces. |

They version **independently**. A framework release does not require a starter
kit release — but a framework *minor* release usually gets one, so new projects
receive the new version.

```
        ┌──────────────────────────┐
        │  libxa/framework         │   the library
        │  (this repository)       │
        └───────────┬──────────────┘
                    │ composer require
                    ▼
        ┌──────────────────────────┐
        │  libxa/libxa             │   the skeleton
        │  (LibxaStack)            │
        └───────────┬──────────────┘
                    │ composer create-project
                    ▼
              a user's new app
```

## Branch model

**`main` is what Packagist publishes. Nothing reaches `main` except through a
reviewed `release/*` or `hotfix/*` pull request.**

```
  feature/auth-guards ─┐
  fix/router-405 ──────┼──▶ develop ──▶ release/v0.9.0 ──▶ main ──(tag v0.9.0)──▶ Packagist
  docs/contributing ───┘        ▲                            │
                                │                            │
                                └────── back-merge ──────────┤
                                                             │
                          hotfix/v0.8.1 ────────────────────▶─┘
```

| Branch | From | Into | Lifetime | Protected |
|---|---|---|---|---|
| `main` | — | — | permanent | ✅ |
| `develop` | `main` | — | permanent | ✅ |
| `feature/*` `fix/*` `docs/*` `test/*` `refactor/*` `chore/*` `perf/*` | `develop` | `develop` | hours–days | — |
| `release/vX.Y.Z` | `develop` | `main` **and** `develop` | days | — |
| `hotfix/vX.Y.Z` | `main` | `main` **and** `develop` | hours | — |

`develop` is the default branch on GitHub, so new pull requests target it
automatically.

## Day-to-day development

```bash
# 1. Start from develop, always
git checkout develop
git pull origin develop
git checkout -b fix/csrf-array-token

# 2. Work. Every bug fix needs a test that fails without the fix.
composer test -- --filter CsrfMiddlewareTest

# 3. Everything CI will run, locally
composer check          # lint + full suite + security audit

# 4. Rebase and open a PR into develop
git fetch origin
git rebase origin/develop
git push -u origin fix/csrf-array-token
gh pr create --base develop
```

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org):

```
fix(router): match optional parameters without a trailing slash
feat(validation): add numeric-aware min/max sizing
feat(request)!: normalise header keys      # ! marks a breaking change
```

## Working on the framework and an app together

Most framework changes are best validated against a real application. Clone
both repositories as **siblings**:

```
your-workspace/
├── libxaframe/     # this repo:  git clone .../libxa.git libxaframe
└── LibxaStack/     # git clone .../LibxaStack.git
```

`composer install` inside `LibxaStack` then **junctions**
`vendor/libxa/framework` onto `../libxaframe`. The vendor directory *is* your
framework working copy, so:

- framework edits take effect on the next request — no `composer update`, no
  `dump-autoload`, not even for brand-new classes (PSR-4 resolves them live);
- the two can never drift apart. They previously did, in both directions, and
  framework fixes silently had no effect on the app.

```bash
(cd libxaframe && composer check)
(cd LibxaStack  && composer check)
```

When no sibling checkout exists — CI, a normal install — the path repository's
glob matches nothing and `libxa/framework` resolves from Packagist instead.

## What CI enforces

Every pull request into `develop` or `main` must pass:

| Job | Checks |
|---|---|
| `Tests · PHP 8.3` / `8.4` | Full suite on both supported versions |
| `Tests` (`--prefer-lowest`) | The *lower* bound of every constraint actually works |
| `PSR-4 contract` | One class per file, no duplicate declarations |
| `Security audit` | No known vulnerabilities in dependencies |
| `Distribution archive` | `src/` ships; `tests/`, `docs/`, `tools/`, `.github/` do not |

The PSR-4 job is not style enforcement. A class the autoloader cannot find is a
fatal `Class not found` at runtime, and a class declared in two files is a
fatal `Cannot redeclare class` the moment both load. **Both shipped in this
framework**, which is why it is a build gate.

## Releasing

Tags are created **by a human**; CI verifies rather than creates them. Full
runbook: [docs/RELEASING.md](docs/RELEASING.md).

```bash
# 1. Cut the release branch — develop is now open for the version after next
git checkout develop && git pull
git checkout -b release/v0.9.0

# 2. Move [Unreleased] in CHANGELOG.md into a dated ## [0.9.0] section

# 3. Verify
composer check

# 4. PR into main, get it reviewed, merge

# 5. Tag on main — annotated, never lightweight
git checkout main && git pull
git tag -a v0.9.0 -m "Release v0.9.0"
git push origin v0.9.0

# 6. Back-merge, or the next release silently reverts this one
git checkout develop
git merge --no-ff origin/main
git push origin develop
```

`release.yml` refuses to publish a tag that is not annotated, not SemVer, not
an ancestor of `main`, or not documented in `CHANGELOG.md` — and re-runs the
full suite at the tagged commit before creating the GitHub Release. Packagist
publishes independently via its push webhook.

### Version numbers

[SemVer](https://semver.org), prefixed `v`. **Below 1.0, Composer treats the
minor number as the compatibility boundary** — `^0.8.0` allows `0.8.9` but not
`0.9.0`:

| Change | Pre-1.0 | Post-1.0 |
|---|---|---|
| Breaking API change | `0.8.3` → `0.9.0` | `1.4.2` → `2.0.0` |
| New backward-compatible feature | `0.8.3` → `0.8.4` | `1.4.2` → `1.5.0` |
| Bug fix | `0.8.3` → `0.8.4` | `1.4.2` → `1.4.3` |

Because a pre-1.0 feature and a pre-1.0 fix share the same slot, anything that
breaks a documented API needs a **minor** bump even when the diff looks small.

## Hotfixes

The only branch that starts from `main`:

```bash
git checkout main && git pull
git checkout -b hotfix/v0.8.1
# fix it, and add a regression test that fails without the fix
# update CHANGELOG.md
gh pr create --base main
# merge → tag on main → back-merge into develop
```

A hotfix without a regression test is not finished — the reason it is urgent is
that nothing caught it.

## Reference

| Document | Covers |
|---|---|
| [CONTRIBUTING.md](CONTRIBUTING.md) | Setup, branch rules, commit convention, test policy |
| [docs/BRANCHING.md](docs/BRANCHING.md) | The branch model in full, plus branch-protection settings |
| [docs/RELEASING.md](docs/RELEASING.md) | Release and hotfix runbook, and the two-repo release order |
| [docs/REPOSITORY_SETUP.md](docs/REPOSITORY_SETUP.md) | One-time GitHub setup: protection rules, tag rules, Packagist webhook |
| [SECURITY.md](SECURITY.md) | Private disclosure, supported versions, security-relevant defaults |
| [CHANGELOG.md](CHANGELOG.md) | What changed in each release |
| [CHANGES.md](CHANGES.md) | Engineering write-up of the stability audits |
