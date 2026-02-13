<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Inertia\Middleware\Middleware;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
final class RootViewPropertyMiddleware extends Middleware
{
    protected string $rootView = 'welcome';
}
