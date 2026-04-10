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
    /** Global middleware stack (applied to every request) */
    protected array $middleware = [
        \Libxa\Multitenancy\Middleware\InitializeTenancy::class,
        \Libxa\Http\Middleware\TrimStringsMiddleware::class,
        \Libxa\Http\Middleware\CsrfMiddleware::class,
    ];

    /** Middleware groups */
    protected array $middlewareGroups = [
        'web' => [
            \Libxa\Http\Middleware\SessionMiddleware::class,
            \Libxa\Http\Middleware\ShareErrorsMiddleware::class,
        ],
        'api' => [
            \Libxa\Http\Middleware\ThrottleMiddleware::class . ':60',
        ],
    ];

    /** Named middleware aliases */
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
            $response = $this->handleException($e, $request);
        }

        return $response;
    }

    /**
     * Terminate the request/response lifecycle.
     */
    public function terminate(Request $request, Response $response): void
    {
        foreach ($this->bootedMiddleware() as $middleware) {
            if (method_exists($middleware, 'terminate')) {
                $middleware->terminate($request, $response);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────

    protected function sendThroughPipeline(Request $request): Response
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        return $router->dispatch($request);
    }

    protected function handleException(\Throwable $e, Request $request): Response
    {
        if ($e instanceof \Libxa\Validation\ValidationException) {
            if ($request->expectsJson() || $request->isAjax()) {
                return $e->toResponse();
            }

            return back()
                ->with('errors', $e->errors())
                ->with('old', $request->except(['password', 'password_confirmation']));
        }

        if ($e instanceof \Libxa\Http\Exceptions\HttpException) {
            return $this->renderHttpException($e);
        }

        $debug = $this->app->config('app.debug')
            || $this->app->env('APP_DEBUG') === 'true'
            || $this->app->env('APP_DEBUG') === true
            || getenv('APP_DEBUG') === 'true'
            || ($_ENV['APP_DEBUG'] ?? '') === 'true';

        if ($debug) {
            return $this->renderDebugException($e);
        }

        return new Response(500, [], $this->renderProductionError());
    }

    protected function renderDebugException(\Throwable $e): Response
    {
        $class   = get_class($e);
        $message = htmlspecialchars($e->getMessage());
        $file    = $e->getFile();
        $line    = $e->getLine();
        $trace   = htmlspecialchars($e->getTraceAsString());

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><title>LibxaFrame — Error</title>
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

        $message = $e->getMessage() ?: ($msgs[$code] ?? 'Something went wrong');

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><title>$code — LibxaFrame</title>
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
        return '<!DOCTYPE html><html><head><title>Server Error</title></head><body style="background:#0a0a0c;color:#fff;text-align:center;padding:50px;font-family:sans-serif;"><h1>500 — Server Error</h1><p>Something went wrong. Please try again later.</p></body></html>';
    }

    protected function bootedMiddleware(): array
    {
        return [];
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
