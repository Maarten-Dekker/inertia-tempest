<?php

declare(strict_types=1);

namespace Inertia\Props;

use Closure;
use Inertia\Contracts\InvokablePropInterface;

use function Tempest\invoke;

final readonly class AlwaysProp implements InvokablePropInterface
{
    /**
     * Create a new always property instance. Always properties are included
     * in every Inertia response, even during partial reloads when only
     * specific props are requested.
     */
    public function __construct(
        private mixed $value,
    ) {}

    /**
     * Resolve the property value.
     */
    #[\Override]
    public function __invoke(): mixed
    {
        if (!is_callable($this->value)) {
            return $this->value;
        }

        if ($this->value instanceof Closure) {
            return invoke($this->value);
        }

        return call_user_func($this->value);
    }
}
