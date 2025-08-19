<?php

declare(strict_types=1);

namespace Inertia\Middleware;

use Override;
use Tempest\Clock\Clock;
use Tempest\Core\AppConfig;
use Tempest\Core\Priority;
use Tempest\Http\Cookie\Cookie;
use Tempest\Http\Cookie\CookieManager;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Session\Session;
use Tempest\Http\Session\SessionConfig;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Support\Str;

#[Priority(Priority::HIGH)]
final readonly class SetCsrfCookieMiddleware implements HttpMiddleware
{
    private const string COOKIE_NAME = 'XSRF-TOKEN';

    public function __construct(
        private Session $session,
        private AppConfig $appConfig,
        private SessionConfig $sessionConfig,
        private CookieManager $cookies,
        private Clock $clock,
    ) {}

    #[Override]
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        $token = $this->session->get(Session::CSRF_TOKEN_KEY) ?? '';

        $this->cookies->add(new Cookie(
            key: self::COOKIE_NAME,
            value: $token,
            expiresAt: $this->clock->now()->plus($this->sessionConfig->expiration),
            path: '/',
            secure: Str\starts_with($this->appConfig->baseUri, 'https'),
        ));

        return $next($request);
    }
}
