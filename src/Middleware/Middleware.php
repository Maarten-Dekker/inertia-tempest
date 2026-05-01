<?php

declare(strict_types=1);

namespace Inertia\Middleware;

use Closure;
use Inertia\Props\OnceProp;
use Inertia\Response as InertiaResponse;
use Inertia\ResponseFactory;
use Inertia\Support\Header;
use Inertia\Support\ResolveErrorProps;
use Override;
use Tempest\Auth\Authentication\Authenticator;
use Tempest\Http\GenericResponse;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Back;
use Tempest\Http\Session\Session;
use Tempest\Http\Status;
use Tempest\Router\Exceptions\ControllerActionHadNoReturn;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Support\Priority;
use Tempest\Vite\ViteConfig;

use function Tempest\root_path;

#[Priority(Priority::FRAMEWORK - 20)]
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
    public function share(): array
    {
        return [
            'errors' => $this->inertia->always($this->errorResolver),
            'auth' => $this->inertia->always(static fn (Authenticator $auth) => [
                'user' => $auth->current(),
            ]),
        ];
    }

    /**
     * Define the props that are shared once and remembered across navigations.
     *
     * @return array<string, callable|OnceProp>
     */
    public function shareOnce(): array
    {
        return [];
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
        $this->inertia->share($this->share());

        foreach ($this->shareOnce() as $key => $value) {
            if ($value instanceof OnceProp) {
                $this->inertia->share($key, $value);
            } else {
                $this->inertia->shareOnce($key, $value);
            }
        }

        $this->inertia->setRootView($this->rootView());

        $this->inertia->resolveUrlUsing($this->urlResolver());

        try {
            $response = $next($request);
        } catch (ControllerActionHadNoReturn $controllerActionHadNoReturn) {
            if ($request->headers->has(Header::INERTIA)) {
                $response = $this->onEmptyResponse();
            } else {
                throw $controllerActionHadNoReturn;
            }
        }

        $response = $response->addHeader(
            key: 'Vary',
            value: Header::INERTIA,
        );

        if ($response->status->isRedirect()) {
            $this->reflash();
        }

        $requestVersion = $request->headers->get(Header::VERSION) ?? '';

        if (
            $request->headers->has(Header::INERTIA)
            && $request->method === Method::GET
            && $requestVersion !== ''
            && $requestVersion !== '0'
            && $requestVersion !== $this->inertia->getVersion()
        ) {
            return $this->onVersionChange($request);
        }

        if (! $request->headers->has(Header::INERTIA) || $response instanceof InertiaResponse) {
            return $response;
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

        if ($response->status->isRedirect() && $this->redirectHasFragment($response) && ! $this->isPrefetch($request)) {
            return $this->onRedirectWithFragment($response);
        }

        return $response;
    }

    /**
     * Determine if the redirect response contains a URL fragment.
     */
    protected function redirectHasFragment(Response $response): bool
    {
        return str_contains($response->getHeader('Location')?->first() ?? '', '#');
    }

    /**
     * Reflash the session data for the next request.
     */
    protected function reflash(): void
    {
        $this->session->reflash();
    }

    /**
     * Handle empty responses.
     */
    protected function onEmptyResponse(): Response
    {
        return new Back();
    }

    /**
     * Determine if the request is a prefetch request.
     */
    protected function isPrefetch(Request $request): bool
    {
        return (
            str_contains($request->headers->get('Purpose') ?? '', 'prefetch')
            || str_contains($request->headers->get('Sec-Purpose') ?? '', 'prefetch')
        );
    }

    /**
     * Handle redirects with URL fragments.
     */
    public function onRedirectWithFragment(Response $response): Response
    {
        return new GenericResponse(
            status: Status::CONFLICT,
            headers: [
                Header::REDIRECT => $response->getHeader('Location')?->first() ?? '',
            ],
        );
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
