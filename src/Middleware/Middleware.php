<?php

declare(strict_types=1);

namespace Inertia\Middleware;

use Closure;
use Inertia\ResponseFactory;
use Inertia\Support\Header;
use Override;
use Tempest\Core\Priority;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Back;
use Tempest\Http\Responses\Ok;
use Tempest\Http\Session\Session;
use Tempest\Http\Status;
use Tempest\Router\Exceptions\ControllerActionHadNoReturn;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Validation\Rule;

use function Tempest\env;
use function Tempest\get;
use function Tempest\root_path;

#[Priority(Priority::HIGH)]
class Middleware implements HttpMiddleware
{
    public Session $session {
        get => get(Session::class);
    }

    public function __construct(
        public readonly ResponseFactory $inertia,
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
        if (env('VITE_ASSET_URL')) {
            return hash('xxh128', (string) env('VITE_ASSET_URL'));
        }

        $manifestPathFromEnv = env('TEMPEST_PLUGIN_CONFIGURATION_PATH');

        if ($manifestPathFromEnv && file_exists($manifestPathFromEnv)) {
            return hash_file('xxh128', $manifestPathFromEnv);
        }

        $manifest = root_path('/public/build/manifest.json');
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
            'errors' => $this->inertia->always($this->resolveValidationErrors($request)),
        ];
    }

    /**
     * Sets the root template loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     */
    public function rootView(Request $request): string
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
     */
    #[Override]
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        $this->inertia->setRootView($this->rootView($request));
        $this->inertia->share($this->share($request));
        $this->inertia->version($this->version());

        $urlResolver = $this->urlResolver();
        if ($urlResolver instanceof \Closure) {
            $this->inertia->resolveUrlUsing($urlResolver);
        }

        try {
            $response = $next($request);
        } catch (ControllerActionHadNoReturn) {
            if (!$request->headers->has(Header::INERTIA)) {
                return new Ok();
            }

            $response = $this->onEmptyResponse();
        }

        $response->addHeader('Vary', Header::INERTIA);

        if (!$request->headers->has(Header::INERTIA)) {
            return $response;
        }

        $currentVersion = $this->inertia->getVersion();
        $clientVersion = $request->headers->get(Header::VERSION) ?? '';

        if ($request->method === Method::GET && $clientVersion && $clientVersion !== $currentVersion) {
            $this->session->reflash();

            return $this->inertia->location($request->uri);
        }

        if (
            $response->status === Status::FOUND
            && in_array($request->method, [Method::POST, Method::PUT, Method::PATCH], true)
        ) {
            $response->setStatus(Status::SEE_OTHER);
        }

        return $response;
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
        $this->session?->reflash();

        return $this->inertia->location($request->uri);
    }

    /**
     * Resolve validation errors for client-side use.
     */
    public function resolveValidationErrors(Request $request): object
    {
        $allErrors = $this->session->get(Session::VALIDATION_ERRORS) ?? [];

        if ($allErrors === []) {
            return new \stdClass();
        }

        $processedErrors = [];
        foreach ($allErrors as $field => $rules) {
            if (is_array($rules) && $rules !== [] && $rules[0] instanceof Rule) {
                $message = $rules[0]->message();

                if (is_array($message)) {
                    $message = $message[0] ?? null;
                }

                if ($message) {
                    $processedErrors[$field] = $message;
                }
            }
        }

        $errorBag = $request->headers->get(Header::ERROR_BAG);

        if ($errorBag) {
            return (object) [
                $errorBag => (object) $processedErrors,
            ];
        }

        return (object) $processedErrors;
    }
}
