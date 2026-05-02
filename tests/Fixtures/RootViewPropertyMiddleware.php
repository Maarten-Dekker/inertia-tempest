<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Inertia\Middleware\Middleware;
use Override;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
final class RootViewPropertyMiddleware extends Middleware
{
    #[Override]
    protected string $rootView = 'welcome';
}
