<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\InvokableProp;
use Inertia\Traits\ResolvesCallables;
use Override;

final readonly class AlwaysProp implements InvokableProp
{
    use ResolvesCallables;

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
    #[Override]
    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->value);
    }
}
