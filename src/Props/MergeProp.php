<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\InvokableProp;
use Inertia\Contracts\Mergeable;
use Inertia\Contracts\Onceable;
use Inertia\Traits\MergesProps;
use Inertia\Traits\ResolvesCallables;
use Inertia\Traits\ResolvesOnce;
use Override;

class MergeProp implements Mergeable, Onceable, InvokableProp
{
    use MergesProps;
    use ResolvesCallables;
    use ResolvesOnce;

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
    #[Override]
    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->value);
    }
}
