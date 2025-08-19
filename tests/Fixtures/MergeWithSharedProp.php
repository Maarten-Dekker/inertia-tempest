<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Inertia\Contracts\ProvidesInertiaPropertyInterface;
use Inertia\Support\PropertyContext;
use Override;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
final readonly class MergeWithSharedProp implements ProvidesInertiaPropertyInterface
{
    /**
     * @param  array<int, mixed>  $items
     */
    public function __construct(
        private array $items = [],
    ) {}

    #[Override]
    public function toInertiaProperty(PropertyContext $prop): array
    {
        $shared = (array) inertia()->getShared($prop->key, []);

        return array_merge($shared, $this->items);
    }
}
