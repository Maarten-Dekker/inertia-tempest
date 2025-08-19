<?php

declare(strict_types=1);

namespace Inertia\Middleware;

use Inertia\LazyBody;
use Override;
use Tempest\Core\Priority;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;

#[Priority(Priority::HIGH)]
class ResolveLazyBodyMiddleware implements HttpMiddleware
{
    #[Override]
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        /** @var \Tempest\Http\Response $response */
        $response = $next($request);

        if ($response->body instanceof LazyBody) {
            $resolvedBody = $response->body->jsonSerialize();

            $response->setBody($resolvedBody);
        }

        return $response;
    }
}
