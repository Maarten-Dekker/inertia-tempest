<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\IgnoreFirstLoadInterface;
use Inertia\Contracts\InvokablePropInterface;
use Inertia\Contracts\MergeableInterface;
use Inertia\Traits\MergesProps;

use function Tempest\invoke;

final class DeferProp implements IgnoreFirstLoadInterface, MergeableInterface, InvokablePropInterface
{
    use MergesProps;

    /**
     * Create a new deferred property instance. Deferred properties are excluded
     * from the initial page load and only evaluated when requested by the
     * frontend, improving initial page performance.
     */
    public function __construct(
        /**
         * @mago-expect strictness/require-parameter-type
         * @var callable
         */
        private $callback,
        private readonly string $group = 'default',
    ) {}

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
