<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\Deferrable;
use Inertia\Contracts\IgnoreFirstLoad;
use Inertia\Contracts\InvokableProp;
use Inertia\Contracts\Mergeable;
use Inertia\Contracts\Onceable;
use Inertia\Traits\DefersProps;
use Inertia\Traits\MergesProps;
use Inertia\Traits\ResolvesCallables;
use Inertia\Traits\ResolvesOnce;
use Override;

final class DeferProp implements Deferrable, IgnoreFirstLoad, Mergeable, Onceable, InvokableProp
{
    use DefersProps;
    use MergesProps;
    use ResolvesCallables;
    use ResolvesOnce;

    /**
     * @var callable
     */
    private $callback;

    /**
     * Create a new deferred property instance. Deferred properties are excluded
     * from the initial page load and only evaluated when requested by the
     * frontend, improving initial page performance.
     */
    public function __construct(callable $callback, string $group = 'default')
    {
        $this->callback = $callback;
        $this->deferred = true;
        $this->deferGroup = $group;
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
