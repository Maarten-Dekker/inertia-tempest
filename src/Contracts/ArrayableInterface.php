<?php

declare(strict_types=1);

namespace Inertia\Contracts;

interface ArrayableInterface
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
