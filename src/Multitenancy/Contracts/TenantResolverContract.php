<?php

namespace Libxa\Multitenancy\Contracts;

use Libxa\Http\Request;

interface TenantResolverContract
{
    /**
     * Resolve the tenant identifier from the incoming HTTP request.
     *
     * @param Request $request
     * @return string|null
     */
    public function resolve(Request $request): ?string;
}
