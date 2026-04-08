<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

class TrimStringsMiddleware
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $this->clean($request);

        return $next($request);
    }

    protected function clean(Request $request): void
    {
        // Recursively trim all input data
        $input = $request->all();
        
        array_walk_recursive($input, function (&$item) {
            if (is_string($item)) {
                $item = trim($item);
            }
        });

        $request->merge($input);
    }
}
