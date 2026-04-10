<?php

namespace Libxa\Multitenancy\Resolvers;

use Libxa\Multitenancy\Contracts\TenantResolverContract;
use Libxa\Http\Request;

class SubdomainResolver implements TenantResolverContract
{
    public function resolve(Request $request): ?string
    {
        $host = $request->header('Host') ?? $_SERVER['HTTP_HOST'] ?? '';
        
        $parts = explode('.', $host);
        
        // For a host like acme.myapp.com, 'acme' is the subdomain if parts > 2 (assuming simple TLD)
        // If testing on localhost exactly (e.g. acme.localhost), parts == 2
        // If testing on 127.0.0.1, parts == 4. We will rely on simple extraction.
        
        if (count($parts) >= 2 && !is_numeric($parts[0])) {
            $subdomain = $parts[0];
            
            // Ignore common non-tenant subdomains
            if (!in_array($subdomain, ['www', 'admin', 'api', 'app'])) {
                return $subdomain;
            }
        }
        
        return null;
    }
}
