<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Override;
use Inertia\Middleware\Middleware;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
final class RootViewPropertyMiddleware extends Middleware
{
    #[Override]
    protected string $rootView = 'welcome';
}
