<?php

if (! function_exists('app')) {
    /**
     * Get the application instance, or resolve a binding.
     */
    function app(?string $abstract = null, array $params = []): mixed
    {
        $instance = \Libxa\Foundation\Application::getInstance();

        if ($abstract === null) {
            return $instance;
        }

        return $instance?->make($abstract, $params);
    }
}

if (! function_exists('env')) {
    /**
     * Get an environment variable.
     */
    function env(string $key, mixed $default = null): mixed
    {
        return \Libxa\Foundation\Application::env($key, $default);
    }
}

if (! function_exists('config')) {
    /**
     * Get a config value.
     */
    function config(string $key, mixed $default = null): mixed
    {
        return app('config')?->get($key, $default) ?? $default;
    }
}

if (! function_exists('view')) {
    /**
     * Render a Blade view.
     */
    function view(string $name, array $data = []): \Libxa\Http\Response
    {
        $content = app('blade')->render($name, $data);
        return new \Libxa\Http\Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $content);
    }
}

if (! function_exists('response')) {
    function response(string $content = '', int $status = 200, array $headers = []): \Libxa\Http\Response
    {
        return new \Libxa\Http\Response($status, $headers, $content);
    }
}

if (! function_exists('json')) {
    function json(mixed $data, int $status = 200): \Libxa\Http\JsonResponse
    {
        return new \Libxa\Http\JsonResponse($data, $status);
    }
}

if (! function_exists('redirect')) {
    function redirect(string $url, int $status = 302): \Libxa\Http\Response
    {
        return \Libxa\Http\Response::redirect($url, $status);
    }
}

if (! function_exists('back')) {
    function back(): \Libxa\Http\Response
    {
        $referer = request()->header('Referer');
        return redirect($referer ?: '/');
    }
}

if (! function_exists('route')) {
    /**
     * Generate a URL for a named route.
     */
    function route(string $name, array $params = []): string
    {
        return app(\Libxa\Router\Router::class)->url($name, $params);
    }
}

if (! function_exists('request')) {
    function request(?string $key = null, mixed $default = null): mixed
    {
        $req = app('request');
        return $key ? $req?->input($key, $default) : $req;
    }
}

if (! function_exists('collect')) {
    function collect(array $items = []): \Libxa\Support\Collection
    {
        return new \Libxa\Support\Collection($items);
    }
}

if (! function_exists('arr')) {
    function arr(array $items = []): \Libxa\Support\Collection
    {
        return collect($items);
    }
}

if (! function_exists('str')) {
    function str(string $value = ''): \Libxa\Support\StringableProxy
    {
        return \Libxa\Support\Str::of($value);
    }
}

if (! function_exists('num')) {
    function num(float|int $value = 0): \Libxa\Support\NumberableProxy
    {
        return \Libxa\Support\Number::for($value);
    }
}

if (! function_exists('asset')) {
    function asset(string $path): string
    {
        $base = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (! function_exists('abort')) {
    function abort(int $code, string $message = ''): never
    {
        throw new \Libxa\Http\Exceptions\HttpException($code, $message);
    }
}

if (! function_exists('report')) {
    function report(\Throwable $e): void
    {
        app('logger')?->error($e->getMessage(), ['exception' => $e]);
    }
}

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (! isset($_SESSION['_token'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (! function_exists('old')) {
    function old(?string $key = null, mixed $default = ''): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (! function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($key === null) return $_SESSION;
        return $_SESSION[$key] ?? $default;
    }
}

if (! function_exists('now')) {
    function now(): \Carbon\Carbon
    {
        return \Carbon\Carbon::now();
    }
}

if (! function_exists('today')) {
    function today(): \Carbon\Carbon
    {
        return \Carbon\Carbon::today();
    }
}

if (! function_exists('carbon')) {
    function carbon(?string $time = null, ?string $tz = null): \Carbon\Carbon
    {
        return new \Carbon\Carbon($time, $tz);
    }
}

if (! function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        if (PHP_SAPI === 'cli') {
            foreach ($vars as $var) var_dump($var);
            exit(1);
        }

        echo '<style>body{background:#0a0a0c;color:#fff;margin:0;padding:20px;font-family:"Fira Code",monospace;}</style>';
        echo '<div style="background:#121214;border:1px solid #2a2a2e;border-radius:12px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,0.5);margin-bottom:20px;">';
        echo '<div style="color:#7ab8ff;font-weight:bold;margin-bottom:15px;display:flex;align-items:center;"><span style="background:#7ab8ff;color:#000;padding:2px 8px;border-radius:4px;margin-right:10px;font-size:12px;">DUMP</span> LibxaFrame Debugger</div>';
        
        foreach ($vars as $var) {
            echo '<pre style="background:#1a1a1e;color:#b4befe;padding:15px;border-radius:8px;overflow:auto;max-height:500px;border-left:4px solid #7ab8ff;margin-bottom:10px;">';
            ob_start();
            var_dump($var);
            echo htmlspecialchars(ob_get_clean());
            echo '</pre>';
        }
        echo '</div>';
        exit(1);
    }
}

if (! function_exists('dump')) {
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre style="background:#1a1a2e;color:#7ab8ff;padding:1rem;border-radius:6px;font-family:monospace;margin:.5rem 0;">';
            var_export($var);
            echo '</pre>';
        }
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (! function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return app()->publicPath($path);
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return app()->resourcePath($path);
    }
}

if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return app()->appPath($path);
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (! function_exists('value')) {
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (! function_exists('tap')) {
    function tap(mixed $value, \Closure $callback): mixed
    {
        $callback($value);
        return $value;
    }
}

if (! function_exists('with')) {
    function with(mixed $value, ?\Closure $callback = null): mixed
    {
        return $callback ? $callback($value) : $value;
    }
}

if (! function_exists('blank')) {
    function blank(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) return true;
        if (is_string($value)) return trim($value) === '';
        return false;
    }
}

if (! function_exists('filled')) {
    function filled(mixed $value): bool { return ! blank($value); }
}

if (! function_exists('data_get')) {
    function data_get(mixed $target, string|array $key, mixed $default = null): mixed
    {
        $keys = is_array($key) ? $key : explode('.', $key);

        foreach ($keys as $segment) {
            if (is_array($target) && isset($target[$segment])) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->$segment)) {
                $target = $target->$segment;
            } else {
                return $default;
            }
        }

        return $target;
    }
}

if (! function_exists('data_set')) {
    function data_set(mixed &$target, string $key, mixed $value): mixed
    {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (! isset($target[$k]) || ! is_array($target[$k])) {
                $target[$k] = [];
            }
            $target = &$target[$k];
        }

        $target[array_shift($keys)] = $value;

        return $target;
    }
}

if (! function_exists('data_forget')) {
    function data_forget(mixed &$target, string|array $keys): void
    {
        $keys = (array) $keys;

        foreach ($keys as $key) {
            if (is_array($target) && array_key_exists($key, $target)) {
                unset($target[$key]);
                continue;
            }

            $parts = explode('.', $key);
            $array = &$target;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[array_shift($parts)]);
        }
    }
}

if (! function_exists('auth')) {
    function auth(?string $guard = null)
    {
        $manager = app(\Libxa\Auth\AuthManager::class);
        
        if (is_null($guard)) {
            return $manager;
        }

        return $manager->guard($guard);
    }
}

if (! function_exists('user')) {
    /**
     * Get the authenticated user.
     */
    function user(?string $guard = null): mixed
    {
        return auth($guard)->user();
    }
}

if (! function_exists('bcrypt')) {
    function bcrypt(string $value, array $options = []): string
    {
        return password_hash($value, PASSWORD_BCRYPT, $options);
    }
}

if (! function_exists('token')) {
    function token(): ?string
    {
        return request()->bearerToken();
    }
}

if (! function_exists('secure_hash')) {
    function secure_hash(string $data): string
    {
        return \Libxa\Auth\LibxaSecure::hash($data);
    }
}

if (! function_exists('storage')) {
    function storage(): \Libxa\Storage\StorageManager
    {
        return app('storage');
    }
}

if (! function_exists('cache')) {
    function cache(?string $key = null, mixed $default = null)
    {
        $manager = app('cache');

        if (is_null($key)) {
            return $manager;
        }

        return $manager->get($key, $default);
    }
}

if (! function_exists('dispatch')) {
    function dispatch(string|object $job, mixed $data = '', ?string $queue = null)
    {
        return app('queue')->push($job, $data, $queue);
    }
}

if (! function_exists('mailer')) {
    function mailer(?string $name = null)
    {
        return app('mail')->mailer($name);
    }
}

if (! function_exists('broadcast')) {
    function broadcast(object|string|null $event = null)
    {
        if (is_null($event)) {
            return app('broadcast');
        }

        if (is_string($event)) {
            return app('broadcast')->connection($event);
        }

        return app('broadcast')->send($event);
    }
}

if (! function_exists('ws')) {
    function ws(): \Libxa\WebSockets\Broadcasting\WsBroadcast
    {
        return app('broadcast');
    }
}

if (! function_exists('event')) {
    /**
     * Dispatch an event.
     */
    function event(object $event): array
    {
        return app('events')->emit($event);
    }
}

if (! function_exists('__')) {
    /**
     * Translate the given message.
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return app('translator')->get($key, $replace, $locale);
    }
}

if (! function_exists('ai')) {
    /**
     * Get the AI manager instance.
     */
    function ai(): \Libxa\Ai\AiManager
    {
        return app('ai');
    }
}

if (! function_exists('storage')) {
    /**
     * Get the storage manager instance.
     */
    function storage(): \Libxa\Storage\StorageManager
    {
        return app('storage');
    }
}

if (! function_exists('vite')) {
    /**
     * Generate Vite asset tags.
     */
    function vite(array|string $entries): string
    {
        return \Libxa\Frontend\ViteManifest::tags($entries);
    }
}

if (! function_exists('log')) {
    function log(?string $message = null, array $context = [], string $level = 'info'): mixed
    {
        $logger = app('logger');
        if (is_null($message)) return $logger;
        return $logger->$level($message, $context);
    }
}

if (! function_exists('abort_if')) {
    function abort_if(bool $condition, int $code, string $message = ''): void
    {
        if ($condition) abort($code, $message);
    }
}

if (! function_exists('rescue')) {
    function rescue(callable $callback, mixed $rescue = null, bool $report = true): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            if ($report) report($e);
            return value($rescue, $e);
        }
    }
}

if (! function_exists('retry')) {
    function retry(int $times, callable $callback, int $sleepMs = 0): mixed
    {
        $attempts = 0;
        while ($attempts < $times) {
            $attempts++;
            try {
                return $callback($attempts);
            } catch (\Throwable $e) {
                if ($attempts >= $times) throw $e;
                if ($sleepMs > 0) usleep($sleepMs * 1000);
            }
        }
        return null;
    }
}

if (! function_exists('tenant')) {
    function tenant(?string $key = null): mixed
    {
        $manager = app('tenant');
        if (is_null($key)) return $manager;
        return $manager->id();
    }
}

if (! function_exists('lang')) {
    function lang(?string $key = null, array $replace = [], ?string $locale = null): mixed
    {
        if (is_null($key)) return app('translator');
        return __($key, $replace, $locale);
    }
}

if (! function_exists('encrypt')) {
    function encrypt(mixed $value, bool $serialize = true): string
    {
        return app('encrypter')->encrypt($value, $serialize);
    }
}

if (! function_exists('decrypt')) {
    function decrypt(string $value, bool $unserialize = true): mixed
    {
        return app('encrypter')->decrypt($value, $unserialize);
    }
}

if (! function_exists('gate')) {
    function gate(): \Libxa\Auth\Access\Gate
    {
        return app('gate');
    }
}

if (! function_exists('can')) {
    function can(string $ability, mixed ...$arguments): bool
    {
        return gate()->check($ability, ...$arguments);
    }
}

if (! function_exists('e')) {
    function e(mixed $value, bool $doubleEncode = true): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', $doubleEncode);
    }
}

if (! function_exists('sanitize')) {
    function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('signed_url')) {
    function signed_url(string $name, array $params = [], int $expiration = 3600): string
    {
        $url = route($name, $params);
        $expires = time() + $expiration;
        $signature = hash_hmac('sha256', $url . $expires, env('APP_KEY', 'secret'));
        
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'expires=' . $expires . '&signature=' . $signature;
    }
}

if (! function_exists('class_basename')) {
    function class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (! function_exists('action')) {
    function action(string $controllerAction, array $params = []): string
    {
        // Simple mapping to route name by removing namespaces and formatting
        $name = strtolower(str_replace(['\\', '@'], ['.', '.'], class_basename($controllerAction)));
        return route($name, $params);
    }
}

if (! function_exists('url')) {
    function url(?string $path = null): string
    {
        $base = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
        
        if (is_null($path)) {
            // Return current URL
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            return $base . $uri;
        }
        
        return $base . '/' . ltrim($path, '/');
    }
}
