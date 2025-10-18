<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Closure;
use Inertia\Middleware\Middleware;
use LogicException;
use Override;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Http\Request;
use Tempest\Http\Response;

#[SkipDiscovery]
final class ExampleMiddleware extends Middleware
{
    protected string $rootView = 'welcome';

    public static int $runCount = 0;

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
        return fn() => '/my-custom-url';
    }

    #[Override]
    public function rootView(): string
    {
        return 'welcome';
    }

    #[\Override]
    public function share(Request $request): array
    {
        self::$runCount++;

        return [
            ...parent::share($request),
            'flash' => [
                'message' => fn() => $request->getCookie('massage'),
            ],
        ];
    }
}
