<?php

declare(strict_types=1);

namespace Inertia\Support;

class PropertyContext
{
    /**
     * Create a new property context instance. The property context provides
     * information about the current property being resolved to objects
     * implementing ProvidesInertiaPropertyInterface.
     *
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public string $key,
        public array $props,
    ) {}
}
