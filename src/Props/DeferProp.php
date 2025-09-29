<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\IgnoreFirstLoad;
use Inertia\Contracts\InvokableProp;
use Inertia\Contracts\Mergeable;
use Inertia\Traits\MergesProps;

use function Tempest\invoke;

final class DeferProp implements IgnoreFirstLoad, Mergeable, InvokableProp
{
    use MergesProps;

    /**
     * @mago-expect lint:property-type
     * @var callable
     */
    private $callback;

    /**
     * Create a new deferred property instance. Deferred properties are excluded
     * from the initial page load and only evaluated when requested by the
     * frontend, improving initial page performance.
     */
    public function __construct(
        /**
         * @var callable
         */
        callable $callback,
        private readonly string $group = 'default',
    ) {
        $this->callback = $callback;
    }

    /**
     * Get the defer group for this property. Properties with the same group
     * are loaded together in a single request, allowing for efficient
     * batching of related deferred data.
     */
    public function group(): ?string
    {
        return $this->group;
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
