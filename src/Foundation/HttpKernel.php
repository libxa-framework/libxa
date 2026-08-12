<?php

declare(strict_types=1);

namespace Libxa\Foundation;

use Libxa\Http\Request;
use Libxa\Http\Response;
use Libxa\Router\Router;

/**
 * HTTP Kernel
 *
 * Orchestrates the full HTTP request → response lifecycle:
 *   1. Boot the application
 *   2. Resolve the router
 *   3. Run the middleware pipeline
 *   4. Dispatch to the matched controller/closure
 *   5. Return the response
 */
class HttpKernel
{
    /**
     * Global middleware stack (applied to every request, before routing).
     */
    protected array $middleware = [
        \Libxa\Multitenancy\Middleware\InitializeTenancy::class,
        \Libxa\Http\Middleware\TrimStringsMiddleware::class,
        \Libxa\Http\Middleware\SessionMiddleware::class,
        \Libxa\Http\Middleware\CsrfMiddleware::class,
    ];

    /**
     * Middleware groups: applied when a route uses them.
     */
    protected array $middlewareGroups = [
        // SessionMiddleware is already in the global stack above; listing it
        // here too made every 'web' route start the session and age its flash
        // data twice, which consumed one-request flash messages before the
        // view could read them.
        'web' => [
            \Libxa\Http\Middleware\ShareErrorsMiddleware::class,
        ],
        'api' => [
            \Libxa\Http\Middleware\ThrottleMiddleware::class . ':60',
        ],
    ];

    /**
     * Named middleware aliases.
     */
    protected array $middlewareAliases = [
        'auth'     => \Libxa\Http\Middleware\AuthMiddleware::class,
        'guest'    => \Libxa\Http\Middleware\GuestMiddleware::class,
        'throttle' => \Libxa\Http\Middleware\ThrottleMiddleware::class,
        'verified' => \Libxa\Http\Middleware\EmailVerifiedMiddleware::class,
    ];

    public function __construct(protected Application $app) {}

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(Request $request): Response
    {
        $this->app->boot();

        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        try {
            $response = $this->sendThroughPipeline($request);
        } catch (\Throwable $e) {
            try {
                $response = $this->handleException($e, $request);
            } catch (\Throwable $fatal) {
                // The handler itself can fail: back() needs a session,
                // renderDebugException needs a working Response, a custom
                // handler may throw. Without this net the process dies with a
                // blank 500 and the *original* exception is lost entirely.
                $response = $this->renderHandlerFailure($e, $fatal);
            }
        }

        return $response;
    }

    /**
     * Last-resort response for when the exception handler itself threw.
     */
    protected function renderHandlerFailure(\Throwable $original, \Throwable $fatal): Response
    {
        error_log('[LibxaFrame] Exception handler failed: ' . $fatal->getMessage()
            . ' (while handling: ' . $original->getMessage() . ')');

        if (! $this->isDebug()) {
            return new Response(500, ['Content-Type' => 'text/html; charset=utf-8'], $this->renderProductionError());
        }

        $body = "Original exception:\n" . $original . "\n\n"
              . "Then the exception handler failed with:\n" . $fatal;

        return new Response(
            500,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            $body
        );
    }

    /**
     * Terminate the request/response lifecycle.
     */
    public function terminate(Request $request, Response $response): void
    {
        // bootedMiddleware() always returned [], so terminate() was a no-op
        // and terminable middleware never ran. Resolve the configured global
        // middleware and call terminate() on whichever declare it.
        foreach ($this->middleware as $pipe) {
            $class = is_string($pipe) ? explode(':', $pipe, 2)[0] : $pipe;

            try {
                $instance = is_object($class) ? $class : $this->app->make($class);
            } catch (\Throwable) {
                continue;
            }

            if (method_exists($instance, 'terminate')) {
                try {
                    $instance->terminate($request, $response);
                } catch (\Throwable $e) {
                    // A failing terminate() must not corrupt an
                    // already-sent response.
                    $this->reportException($e);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────

    protected function sendThroughPipeline(Request $request): Response
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        // Run global middleware then dispatch
        $pipeline = new \Libxa\Router\Pipeline($this->app);
        return $pipeline
            ->send($request)
            ->through($this->middleware)
            ->then(fn(Request $req) => $router->dispatch($req));
    }

    /**
     * Whether the app is in debug mode (verbose error pages).
     */
    protected function isDebug(): bool
    {
        $configured = $this->app->config('app.debug');

        if ($configured !== null) {
            return (bool) $configured;
        }

        return Application::envBool('APP_DEBUG', false);
    }

    protected function handleException(\Throwable $e, Request $request): Response
    {
        if ($e instanceof \Libxa\Validation\ValidationException) {
            if ($request->expectsJson()) {
                return $e->toResponse();
            }

            return \Libxa\Http\Response::back()
                ->with('errors', $e->errors())
                ->with('old', $request->except(['password', 'password_confirmation', '_token']));
        }

        if ($e instanceof \Libxa\Http\Exceptions\HttpException) {
            $response = $request->expectsJson()
                ? new \Libxa\Http\JsonResponse(
                    ['message' => $e->getMessage() ?: 'HTTP error'],
                    $e->getStatusCode()
                )
                : $this->renderHttpException($e);

            // Headers carried by the exception (Retry-After on a 429,
            // WWW-Authenticate on a 401) used to be dropped on the floor.
            return $e->getHeaders() === [] ? $response : $response->withHeaders($e->getHeaders());
        }

        // Unexpected exceptions are always worth a log line, in every
        // environment: production previously swallowed them silently.
        $this->reportException($e);

        if (! $this->isDebug()) {
            if ($request->expectsJson()) {
                return new \Libxa\Http\JsonResponse(['message' => 'Server Error'], 500);
            }

            return new Response(500, ['Content-Type' => 'text/html; charset=utf-8'], $this->renderProductionError());
        }

        if ($request->expectsJson()) {
            return new \Libxa\Http\JsonResponse([
                'message'   => $e->getMessage(),
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => array_slice($e->getTrace(), 0, 20),
            ], 500);
        }

        return $this->renderDebugException($e);
    }

    protected function reportException(\Throwable $e): void
    {
        try {
            if ($this->app->has('logger')) {
                $this->app->make('logger')->error($e->getMessage(), ['exception' => $e]);
                return;
            }
        } catch (\Throwable) {
            // Fall through to error_log below.
        }

        error_log('[LibxaFrame] ' . $e::class . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
    }

    protected function renderDebugException(\Throwable $e): Response
    {
        // Every interpolated value is attacker-influenced (an exception
        // message routinely contains user input), so all of them are escaped:
        // $file/$class/$line used to be injected into the HTML raw.
        $class   = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file    = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line    = (int) $e->getLine();
        $trace   = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><title>Error | LibxaFrame</title>
        <style>
            body { font-family: system-ui; background: #0f0f0f; color: #e0e0e0; margin: 0; padding: 2rem; }
            .box { background: #1a1a2e; border: 1px solid #c0392b; border-radius: 8px; padding: 2rem; max-width: 900px; margin: 0 auto; }
            h1 { color: #e74c3c; font-size: 1.5rem; margin: 0 0 1rem; }
            .file { font-family: monospace; color: #7ab8ff; background: #0d1624; padding: .5rem 1rem; border-radius: 4px; margin: 1rem 0; }
            h2 { color: #aaa; font-size: 1rem; margin: 1.5rem 0 .5rem; }
            pre { background: #060d18; color: #a8c8f0; padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: 12px; line-height: 1.6; }
            .badge { display:inline-block; background:#c0392b; color:#fff; font-size:11px; padding:2px 8px; border-radius:20px; margin-bottom:1rem; }
        </style></head>
        <body>
        <div class="box">
            <div class="badge">LibxaFrame Error</div>
            <h1>$message</h1>
            <p style="color:#aaa; font-size:.9rem;">$class</p>
            <div class="file">📄 $file : line $line</div>
            <h2>Stack Trace</h2>
            <pre>$trace</pre>
        </div>
        </body></html>
        HTML;

        return new Response(500, ['Content-Type' => 'text/html'], $html);
    }

    protected function renderHttpException(\Libxa\Http\Exceptions\HttpException $e): Response
    {
        $code = $e->getStatusCode();
        $msgs = [
            400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
            404 => 'Not Found',   419 => 'Page Expired', 422 => 'Unprocessable Entity',
            429 => 'Too Many Requests', 500 => 'Internal Server Error',
        ];

        $message = htmlspecialchars(
            $e->getMessage() ?: ($msgs[$code] ?? 'Something went wrong'),
            ENT_QUOTES,
            'UTF-8'
        );
        $code = (int) $code;

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><title>$code | LibxaFrame</title>
        <style>
            body { font-family: system-ui; background: #0a0a0c; color: #fff; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; text-align: center; }
            .box { padding: 3rem; background: #121214; border: 1px solid #2a2a2e; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
            h1 { font-size: 6rem; margin: 0; background: linear-gradient(135deg, #7ab8ff, #b4befe); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            p { font-size: 1.2rem; color: #aaa; margin: 1rem 0 2rem; }
            .btn { display: inline-block; padding: 10px 25px; background: #7ab8ff; color: #000; text-decoration: none; border-radius: 8px; font-weight: bold; transition: 0.3s; }
            .btn:hover { background: #b4befe; transform: translateY(-2px); }
        </style></head>
        <body>
        <div class="box">
            <h1>$code</h1>
            <p>$message</p>
            <a href="/" class="btn">Return Home</a>
        </div>
        </body></html>
        HTML;

        return new Response($code, ['Content-Type' => 'text/html'], $html);
    }

    protected function renderProductionError(): string
    {
        return '<!DOCTYPE html><html><head><title>Server Error</title></head><body style="background:#0a0a0c;color:#fff;text-align:center;padding:50px;font-family:sans-serif;"><h1>500 Server Error</h1><p>Something went wrong. Please try again later.</p></body></html>';
    }

    /**
     * The global middleware stack, in execution order.
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Append middleware to the global stack (used by packages/modules).
     */
    public function pushMiddleware(string $middleware): static
    {
        if (! in_array($middleware, $this->middleware, true)) {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }
}
