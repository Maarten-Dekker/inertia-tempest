<?php

declare(strict_types=1);

namespace Inertia\Traits;

trait DefersProps
{
    /**
     * Indicates if the property should be deferred.
     */
    protected bool $deferred = false;

    /**
     * The defer group.
     */
    protected string $deferGroup = 'default';

    /**
     * Mark this property as deferred. Deferred properties are excluded
     * from the initial page load and only evaluated when requested by the
     * frontend, improving initial page performance.
     */
    public function defer(string $group = 'default'): static
    {
        $this->deferred = true;
        $this->deferGroup = $group;

        return $this;
    }

    /**
     * Determine if this property should be deferred.
     */
    public function shouldDefer(): bool
    {
        return $this->deferred;
    }

    /**
     * Get the defer group for this property. Properties with the same group
     * are loaded together in a single request, allowing for efficient
     * batching of related deferred data.
     */
    public function group(): string
    {
        return $this->deferGroup;
    }
}
