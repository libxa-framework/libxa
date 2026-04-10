<?php

namespace Libxa\Multitenancy\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;
use Closure;
use Libxa\Multitenancy\TenancyManager;
use Libxa\Foundation\Application;

class InitializeTenancy
{
    /**
     * Handle an incoming request and boot tenancy.
     *
     * @param  \Libxa\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $app = Application::getInstance();
        
        $manager = new TenancyManager($app);
        $manager->initialize($request);

        // Bind it into the container so controllers can retrieve the exact booted instance
        $app->instance(TenancyManager::class, $manager);

        return $next($request);
    }

}
