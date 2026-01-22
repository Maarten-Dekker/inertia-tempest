<?php

declare(strict_types=1);

namespace Inertia\Middleware;

use Closure;
use Inertia\LazyBody;
use Inertia\Response as InertiaResponse;
use Inertia\ResponseFactory;
use Inertia\Support\Header;
use Inertia\Support\ResolveErrorProps;
use Inertia\Support\SessionKey;
use Override;
use Tempest\Auth\Authentication\Authenticator;
use Tempest\Core\Priority;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Back;
use Tempest\Http\Session\Session;
use Tempest\Http\Status;
use Tempest\Router\Exceptions\ControllerActionHadNoReturn;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Vite\ViteConfig;

use function Tempest\root_path;

#[Priority(Priority::HIGH)]
class Middleware implements HttpMiddleware
{
    public function __construct(
        protected readonly ResponseFactory $inertia,
        protected readonly Session $session,
        private readonly ViteConfig $viteConfig,
        private readonly ResolveErrorProps $errorResolver,
    ) {}

    /**
     * The root template loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     */
    protected string $rootView = 'inertia.view.php';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(): ?string
    {
        $manifest = root_path(sprintf(
            '/public/%s/%s',
            trim($this->viteConfig->buildDirectory, '/'),
            ltrim($this->viteConfig->manifest, '/'),
        ));

        if (file_exists($manifest)) {
            return hash_file('xxh128', $manifest);
        }

        return null;
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            'errors' => $this->inertia->always($this->errorResolver),
            'auth' => $this->inertia->always(static fn (Authenticator $auth) => [
                'user' => $auth->current(),
            ]),
            'flash' => [
                'message' => fn () => $this->session->get('message'),
            ],
        ];
    }

    /**
     * Sets the root template loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     */
    public function rootView(): string
    {
        return $this->rootView;
    }

    /**
     * Define a callback that returns the relative URL.
     */
    public function urlResolver(): ?Closure
    {
        return null;
    }

    /**
     * This is the core of the Inertia middleware. It checks for Inertia headers,
     * handles asset versioning, and modifies the response accordingly.
     *
     * @throws ControllerActionHadNoReturn
     */
    #[Override]
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        $this->inertia->version($this->version(...));
        $this->inertia->share($this->share($request));
        $this->inertia->setRootView($this->rootView());

        if (($urlResolver = $this->urlResolver()) instanceof Closure) {
            $this->inertia->resolveUrlUsing($urlResolver);
        }

        try {
            $response = $next($request);
        } catch (ControllerActionHadNoReturn $controllerActionHadNoReturn) {
            if ($request->headers->has(Header::INERTIA)) {
                $response = $this->onEmptyResponse();
            } else {
                throw $controllerActionHadNoReturn;
            }
        }

        if ($response->body instanceof LazyBody) {
            $resolvedBody = $response->body->jsonSerialize();
            $response = $response->setBody($resolvedBody);
        }

        $response = $response->addHeader(
            key: 'Vary',
            value: Header::INERTIA,
        );

        if ($response->status->isRedirect()) {
            $this->reflash();
        }

        if (! $request->headers->has(Header::INERTIA) || $response instanceof InertiaResponse) {
            return $response;
        }

        if (
            $request->method === Method::GET
            && ($request->headers->get(Header::VERSION) ?? '') !== $this->inertia->getVersion()
        ) {
            $response = $this->onVersionChange($request);
        }

        if ($response->status === Status::OK && ($response->body === '' || $response->body === null)) {
            $response = $this->onEmptyResponse();
        }

        if (
            $response->status === Status::FOUND
            && in_array($request->method, [Method::PUT, Method::PATCH, Method::DELETE], true)
        ) {
            return $response->setStatus(Status::SEE_OTHER);
        }

        return $response;
    }

    /**
     * Reflash the session data for the next request.
     */
    protected function reflash(): void
    {
        $flashed = $this->inertia->getFlashed();

        if ($flashed !== []) {
            $this->session->flash(SessionKey::FlashData->value, $flashed);
        }
    }

    /**
     * Handle empty responses.
     */
    protected function onEmptyResponse(): Response
    {
        return new Back();
    }

    /**
     * Handle version changes.
     */
    public function onVersionChange(Request $request): Response
    {
        $this->session->reflash();

        return $this->inertia->location($request->uri);
    }
}
