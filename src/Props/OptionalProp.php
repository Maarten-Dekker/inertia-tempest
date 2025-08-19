<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\IgnoreFirstLoadInterface;
use Inertia\Contracts\InvokablePropInterface;

use function Tempest\invoke;

class OptionalProp implements IgnoreFirstLoadInterface, InvokablePropInterface
{
    /**
     * Create a new optional property instance. Optional properties are only
     * included when explicitly requested via partial reloads, reducing
     * initial payload size and improving performance.
     */
    public function __construct(
        /**
         * @mago-expect strictness/require-parameter-type
         * @var callable
         */
        private $callback,
    ) {}

    /**
     * Resolve the property value.
     */
    #[\Override]
    public function __invoke(): mixed
    {
        return invoke($this->callback);
    }
}
