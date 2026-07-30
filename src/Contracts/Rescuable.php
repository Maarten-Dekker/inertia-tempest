<?php

declare(strict_types=1);

namespace Inertia\Contracts;

interface Rescuable
{
    /**
     * Determine if resolution errors should be rescued.
     */
    public function shouldRescue(): bool;
}
