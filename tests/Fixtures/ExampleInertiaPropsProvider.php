<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Inertia\Contracts\ProvidesInertiaProperties;
use Inertia\Support\RenderContext;
use Override;

final readonly class ExampleInertiaPropsProvider implements ProvidesInertiaProperties
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        private array $props,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toInertiaProperties(RenderContext $context): iterable
    {
        return $this->props;
    }
}
