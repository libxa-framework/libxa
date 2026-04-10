<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Libxa\Multitenancy\TenancyManager;
use Libxa\Foundation\Application;
use Libxa\Http\Request;

class TenancyTest extends TestCase
{
    public function test_subdomain_resolver_identifies_tenant()
    {
        $resolver = new \Libxa\Multitenancy\Resolvers\SubdomainResolver();
        
        $request = new Request('GET', '/', ['Host' => 'acme.myapp.com']);
        $this->assertEquals('acme', $resolver->resolve($request));

        $request = new Request('GET', '/', ['Host' => 'nike.localhost']);
        $this->assertEquals('nike', $resolver->resolve($request));
    }

    public function test_header_resolver_identifies_tenant()
    {
        $resolver = new \Libxa\Multitenancy\Resolvers\HeaderResolver();
        
        $request = $this->createMock(Request::class);
        $request->method('header')->with('X-Tenant-ID')->willReturn('google');
        
        $this->assertEquals('google', $resolver->resolve($request));
    }
}
