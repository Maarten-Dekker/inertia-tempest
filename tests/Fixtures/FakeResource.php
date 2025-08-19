<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Inertia\Contracts\ArrayableInterface;
use Override;

final readonly class FakeResource implements ArrayableInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private array $data,
    ) {}

    #[Override]
    public function toArray(): array
    {
        return $this->data;
    }
}
