<?php

declare(strict_types=1);

namespace Inertia\Contracts;

interface InvokablePropInterface
{
    public function __invoke(): mixed;
}
