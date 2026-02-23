<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\IgnoreFirstLoad;
use Inertia\Contracts\InvokableProp;
use Inertia\Contracts\Onceable;
use Inertia\Traits\ResolvesCallables;
use Inertia\Traits\ResolvesOnce;
use Override;

class OptionalProp implements IgnoreFirstLoad, Onceable, InvokableProp
{
    use ResolvesCallables;
    use ResolvesOnce;

    /**
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
    #[Override]
    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->callback);
    }
}
