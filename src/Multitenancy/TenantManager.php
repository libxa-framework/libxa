<?php

declare(strict_types=1);

namespace Libxa\Multitenancy;

use Libxa\Foundation\Application;

class TenantManager
{
    protected ?string $tenantId = null;

    public function __construct(protected Application $app)
    {
    }

    /**
     * Start the multitenancy logic.
     */
    public function initialize(?string $tenantId = null): void
    {
        if ($tenantId) {
            $this->tenantId = $tenantId;
            return;
        }

        // Default logic: Detect from domain or header
        if ($this->app->isHttp()) {
            $request = $this->app->make('request');
            $this->tenantId = $request->header('X-Tenant-Id') ?: $request->host();
        }
    }

    /**
     * Get the current tenant ID.
     */
    public function id(): ?string
    {
        if (!$this->tenantId) {
            $this->initialize();
        }

        return $this->tenantId;
    }

    /**
     * Check if a tenant is identified.
     */
    public function check(): bool
    {
        return $this->id() !== null;
    }

    /**
     * Set the current tenant manually.
     */
    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }
}
