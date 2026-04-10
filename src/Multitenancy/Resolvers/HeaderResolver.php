<?php

namespace Libxa\Multitenancy\Resolvers;

use Libxa\Multitenancy\Contracts\TenantResolverContract;
use Libxa\Http\Request;

class HeaderResolver implements TenantResolverContract
{
    public function resolve(Request $request): ?string
    {
        return $request->header('X-Tenant-ID') ?? null;
    }
}
