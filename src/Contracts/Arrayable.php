<?php

declare(strict_types=1);

namespace Inertia\Contracts;

interface Arrayable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
