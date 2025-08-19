<?php

declare(strict_types=1);

namespace Inertia\Support;

class RenderContext
{
    /**
     * Create a new render context instance. The render context provides
     * information about the current Inertia render operation to objects
     * implementing ProvidesInertiaPropertiesInterface.
     */
    public function __construct(
        public string $component,
    ) {}
}
