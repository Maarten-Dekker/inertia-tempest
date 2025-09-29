<?php

declare(strict_types=1);

namespace Inertia\Contracts;

use Inertia\Support\RenderContext;

interface ProvidesInertiaProperties
{
    /**
     * Get the properties to be provided to Inertia. This method allows objects
     * to dynamically provide properties that will be serialized and sent
     * to the frontend.
     *
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): iterable;
}
