<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\IgnoreFirstLoad;
use Inertia\Contracts\InvokableProp;
use Override;

use function Tempest\invoke;

/**
 * @deprecated Use OptionalProp instead for clearer semantics.
 */
final class LazyProp implements IgnoreFirstLoad, InvokableProp
{
    /**
     * @var callable
     */
    private $callback;

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
        return invoke($this->callback);
    }
}
