<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\IgnoreFirstLoad;
use Inertia\Contracts\InvokableProp;

use function Tempest\invoke;

class OptionalProp implements IgnoreFirstLoad, InvokableProp
{
    /**
     * @mago-expect lint:property-type
     * @var callable
     */
    private $callback;

    /**
     * Create a new optional property instance. Optional properties are only
     * included when explicitly requested via partial reloads, reducing
     * initial payload size and improving performance.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Resolve the property value.
     */
    #[\Override]
    public function __invoke(): mixed
    {
        return invoke($this->callback);
    }
}
