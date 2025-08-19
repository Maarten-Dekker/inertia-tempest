<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\InvokablePropInterface;
use Inertia\Contracts\MergeableInterface;
use Inertia\Traits\MergesProps;

use function Tempest\invoke;

class MergeProp implements MergeableInterface, InvokablePropInterface
{
    use MergesProps;

    /**
     * Create a new merge property instance. Merge properties are combined
     * with existing client-side data during partial reloads instead of
     * completely replacing the property value.
     */
    public function __construct(
        protected mixed $value,
    ) {
        $this->merge = true;
    }

    /**
     * Resolve the property value.
     */
    #[\Override]
    public function __invoke(): mixed
    {
        return is_callable($this->value) ? invoke($this->value) : $this->value;
    }
}
