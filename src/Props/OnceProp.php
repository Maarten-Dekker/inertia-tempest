<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\InvokableProp;
use Inertia\Contracts\Onceable;
use Inertia\Traits\ResolvesCallables;
use Inertia\Traits\ResolvesOnce;
use Override;

class OnceProp implements Onceable, InvokableProp
{
    use ResolvesCallables;
    use ResolvesOnce;

    /**
     * @var callable
     */
    private $callback;

    /**
     * Create a new once property instance.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->once = true;
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
