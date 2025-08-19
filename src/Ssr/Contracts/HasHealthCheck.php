<?php

declare(strict_types=1);

namespace Inertia\Ssr\Contracts;

interface HasHealthCheck
{
    /**
     * Determine if the SSR server is healthy and responsive.
     */
    public function isHealthy(): bool;
}
