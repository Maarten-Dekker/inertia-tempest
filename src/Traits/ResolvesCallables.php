<?php

declare(strict_types=1);

namespace Inertia\Traits;

use Closure;

use function Tempest\invoke;

trait ResolvesCallables
{
    /**
     * Resolve a value that may be callable.
     */
    protected function resolveCallable(mixed $value): mixed
    {
        if (! is_callable($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Closure) {
            return invoke($value);
        }

        return $value();
    }
}
