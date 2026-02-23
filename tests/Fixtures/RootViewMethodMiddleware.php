<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Closure;
use Inertia\Middleware\Middleware;
use LogicException;
use Override;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Http\Response;

#[SkipDiscovery]
final class RootViewMethodMiddleware extends Middleware
{
    #[Override]
    protected function onEmptyResponse(): Response
    {
        throw new LogicException('An empty Inertia response was returned.');
    }

    #[Override]
    public function version(): ?string
    {
        return 'test-version-from-middleware';
    }

    #[Override]
    public function urlResolver(): ?Closure
    {
        return static fn () => '/my-custom-url';
    }

    #[Override]
    public function rootView(): string
    {
        return 'welcome';
    }

    #[Override]
    public function shareOnce(): array
    {
        return [
            'permissions' => static fn () => ['admin' => true],
            'settings' => $this->inertia
                ->once(static fn () => ['theme' => 'dark'])
                ->as('app-settings')
                ->until(60),
        ];
    }
}
