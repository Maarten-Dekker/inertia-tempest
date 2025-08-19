<?php

declare(strict_types=1);

namespace Inertia\Middleware;

use Inertia\ResponseFactory;
use Override;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;

#[SkipDiscovery]
readonly class EncryptHistoryMiddleware implements HttpMiddleware
{
    public function __construct(
        private ResponseFactory $inertia,
    ) {}

    /**
     * Handle the incoming request and enable history encryption. This middleware
     * enables encryption of the browser history state, providing additional
     * security for sensitive data in Inertia responses.
     */
    #[Override]
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        $this->inertia->encryptHistory();

        return $next($request);
    }
}
