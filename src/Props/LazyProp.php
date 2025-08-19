<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\IgnoreFirstLoadInterface;
use Inertia\Contracts\InvokablePropInterface;

use function Tempest\invoke;

/**
 * @deprecated Use OptionalProp instead for clearer semantics.
 */
final class LazyProp implements IgnoreFirstLoadInterface, InvokablePropInterface
{
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
