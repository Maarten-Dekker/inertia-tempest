<?php

declare(strict_types=1);

namespace Inertia;

use Closure;
use DateInterval;
use DateTimeImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Inertia\Configs\InertiaConfig;
use Inertia\Contracts\ArrayableInterface;
use Inertia\Contracts\IgnoreFirstLoadInterface;
use Inertia\Contracts\InvokablePropInterface;
use Inertia\Contracts\MergeableInterface;
use Inertia\Contracts\ProvidesInertiaPropertiesInterface;
use Inertia\Contracts\ProvidesInertiaPropertyInterface;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferProp;
use Inertia\Ssr\Contracts\Gateway;
use Inertia\Ssr\Response as SsrResponse;
use Inertia\Support\Header;
use Inertia\Support\PaginatorAdapter;
use Inertia\Support\PropertyContext;
use Inertia\Support\RenderContext;
use Inertia\Views\InertiaView;
use Tempest\Http\ContentType;
use Tempest\Http\IsResponse;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Response as HttpResponse;
use Tempest\Http\Status;
use Tempest\Support\Arr;
use Tempest\Support\Arr\ArrayInterface;
use Tempest\Support\Paginator\PaginatedData;

use function Tempest\get;
use function Tempest\invoke;
use function Tempest\Support\Arr\to_array;

final class Response implements HttpResponse
{
    use IsResponse;

    /**
     * The view data.
     *
     * @var array<string, mixed>
     */
    private array $viewData = [];

    /**
     * The cache duration settings.
     *
     * @var array<int, mixed>
     */
    private array $cacheFor = [];

    private Request $request {
        get => get(Request::class);
    }

    private InertiaConfig $config {
        get => get(InertiaConfig::class);
    }

    private Gateway $gateway {
        get => get(Gateway::class);
    }

    /**
     * Create a new Inertia response instance.
     *
     * @param  array<array-key, mixed|\Inertia\Contracts\ProvidesInertiaPropertiesInterface>  $props
     */
    public function __construct(
        private readonly string $component,
        private array|ArrayInterface $props,
        private string $rootView = 'inertia.view.php',
        private readonly string $version = '',
        private readonly bool $clearHistory = false,
        private readonly bool $encryptHistory = false,
        private readonly ?Closure $urlResolver = null,
    ) {
        $this->body = new LazyBody(function () {
            $this->props = $this->normalizeProps($this->props);
            $resolvedProps = $this->resolveProperties($this->props);

            $page = array_merge(
                [
                    'component' => $this->component,
                    'props' => $resolvedProps,
                    'url' => $this->getUrl(),
                    'version' => $this->version,
                    'clearHistory' => $this->resolveClearHistory(),
                    'encryptHistory' => $this->encryptHistory,
                ],
                $this->resolveMergeProps(),
                $this->resolveDeferredProps(),
                $this->resolveCacheDirections(),
            );

            return $this->resolveBody($page);
        });
    }

    /**
     * Add additional properties to the page.
     *
     * @param  string|array<string, mixed>|\Inertia\Contracts\ProvidesInertiaPropertiesInterface  $key
     */
    public function with(string|array|ProvidesInertiaPropertiesInterface $key, mixed $value = null): self
    {
        if ($key instanceof ProvidesInertiaPropertiesInterface) {
            $this->props[] = $key;
        } elseif (is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this;
    }

    /**
     * Add additional data to the view.
     */
    public function withViewData(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * Set the root view.
     */
    public function rootView(string $rootView): self
    {
        $this->rootView = $rootView;

        return $this;
    }

    /**
     * Set the cache duration for the response.
     *
     * @param  string|array<int, mixed>  $cacheFor
     */
    public function cache(string|array $cacheFor): self
    {
        $this->cacheFor = is_array($cacheFor) ? $cacheFor : [$cacheFor];

        return $this;
    }

    /**
     * Resolve the properties for the response.
     *
     * @param  array<array-key, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveProperties(array $props): array
    {
        $props = $this->resolveInertiaPropsProviders($props);
        $props = $this->resolvePartialProperties($props);
        $props = $this->resolveAlways($props);

        return $this->resolvePropertyInstances($props);
    }

    /**
     * Resolve the ProvidesInertiaPropertiesInterface props.
     *
     * @param  array<array-key, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveInertiaPropsProviders(array $props): array
    {
        $newProps = [];

        $renderContext = new RenderContext($this->component);

        foreach ($props as $key => $value) {
            if (is_numeric($key) && $value instanceof ProvidesInertiaPropertiesInterface) {
                /** @var array<string, mixed> $inertiaProps */
                $inertiaProps = to_array($value->toInertiaProperties($renderContext));
                $newProps = array_merge($newProps, $inertiaProps);
            } else {
                $newProps[$key] = $value;
            }
        }

        return $newProps;
    }

    /**
     * Resolve properties for partial requests. Filters properties based on
     * 'only' and 'except' headers from the client, allowing for selective
     * data loading to improve performance.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolvePartialProperties(array $props): array
    {
        if (!$this->isPartial()) {
            return array_filter($props, static function ($prop) {
                return !($prop instanceof IgnoreFirstLoadInterface);
            });
        }

        $only = $this->parsePartialHeader(Header::PARTIAL_ONLY);
        $except = $this->parsePartialHeader(Header::PARTIAL_EXCEPT);

        if ($only !== []) {
            $newProps = [];

            foreach ($only as $key) {
                $value = Arr\get_by_key($props, $key);

                if ($value instanceof ArrayInterface) {
                    $value = $value->toArray();
                }

                $newProps = Arr\set_by_key($newProps, $key, $value);
            }

            $props = $newProps;
        }

        if ($except !== []) {
            Support\Arr\forget_keys($props, $except);
        }

        return $props;
    }

    /**
     * Resolve `always` properties that should always be included.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveAlways(array $props): array
    {
        $always = array_filter($this->props, static function ($prop) {
            return $prop instanceof AlwaysProp;
        });

        return array_merge($always, $props);
    }

    /**
     * Resolve all necessary class instances in the given props.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolvePropertyInstances(
        array $props,
        bool $unpackDotProps = true,
        ?string $parentKey = null,
    ): array {
        $result = [];

        foreach ($props as $key => $value) {
            if ($value instanceof Closure) {
                $value = invoke($value);
            } elseif ($value instanceof InvokablePropInterface) {
                $value = $value();
            }

            if ($this->config->transform_pagination && $value instanceof PaginatedData) {
                $value = new PaginatorAdapter($value);
            }

            $currentKey = $parentKey ? ($parentKey . '.' . $key) : $key;

            if ($value instanceof ProvidesInertiaPropertyInterface) {
                $value = $value->toInertiaProperty(new PropertyContext($currentKey, $props));
            }

            if ($value instanceof ArrayableInterface || $value instanceof ArrayInterface) {
                $value = $value->toArray();
            }

            if ($value instanceof PromiseInterface) {
                $value = $value->wait();
            }

            if ($value instanceof HttpResponse) {
                $value = $value->body;
            }

            if (is_array($value)) {
                $value = $this->resolvePropertyInstances($value, false, $currentKey);
            }

            if ($unpackDotProps && is_string($key) && str_contains($key, '.')) {
                $result = Arr\set_by_key($result, $key, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Resolve the cache directions for the response.
     *
     * @return array<string, mixed>
     */
    public function resolveCacheDirections(): array
    {
        if ($this->cacheFor === []) {
            return [];
        }

        return [
            'cache' => array_map(function ($value) {
                if ($value instanceof DateInterval) {
                    return new DateTimeImmutable('@0')
                        ->add($value)
                        ->getTimestamp();
                }

                return intval($value);
            }, $this->cacheFor),
        ];
    }

    /**
     * Resolve merge props configuration for client-side prop merging.
     *
     * @return array<string, mixed>
     */
    public function resolveMergeProps(): array
    {
        $resetProps = $this->parsePartialHeader(Header::RESET);
        $onlyProps = $this->parsePartialHeader(Header::PARTIAL_ONLY);
        $exceptProps = $this->parsePartialHeader(Header::PARTIAL_EXCEPT);

        $mergeProps = Arr\filter(
            $this->props,
            fn($prop, $key) => (
                $prop instanceof MergeableInterface &&
                $prop->shouldMerge() &&
                !in_array($key, $resetProps, true) &&
                ($onlyProps === [] || in_array($key, $onlyProps, true)) &&
                !in_array($key, $exceptProps, true)
            ),
        );

        $deepMergeProps = Arr\keys(Arr\filter($mergeProps, fn($prop) => $prop->shouldDeepMerge()));

        $matchPropsOn = Arr\values(Arr\flat_map($mergeProps, fn(MergeableInterface $prop, $key) => Arr\map_iterable(
            $prop->matchesOn(),
            fn($strategy) => $key . '.' . $strategy,
        )));

        $mergeProps = Arr\keys(Arr\filter($mergeProps, fn($prop) => !$prop->shouldDeepMerge()));

        return array_filter(
            [
                'mergeProps' => $mergeProps,
                'deepMergeProps' => $deepMergeProps,
                'matchPropsOn' => $matchPropsOn,
            ],
            fn($prop) => $prop !== [],
        );
    }

    /**
     * Resolve deferred props configuration for client-side lazy loading.
     *
     * @return array<string, mixed>
     */
    public function resolveDeferredProps(): array
    {
        if ($this->isPartial()) {
            return [];
        }

        $deferredProps = Arr\map_iterable(
            Arr\group_by(
                Arr\map_iterable(Arr\filter($this->props, fn($prop) => $prop instanceof DeferProp), fn($prop, $key) => [
                    'key' => $key,
                    'group' => $prop->group(),
                ]),
                fn($item) => $item['group'],
            ),
            fn($group) => Arr\pluck($group, 'key'),
        );

        return Arr\is_empty($deferredProps) ? [] : ['deferredProps' => $deferredProps];
    }

    /**
     * Determine if the request is a partial request.
     */
    public function isPartial(): bool
    {
        return $this->request->headers->get(Header::PARTIAL_COMPONENT) === $this->component;
    }

    /**
     * Normalize the props to an array.
     *
     * @return array<string, mixed>
     */
    private function normalizeProps(array|ArrayInterface $props): array
    {
        return ($props instanceof ArrayInterface) ? $props->toArray() : $props;
    }

    /**
     * Resolve the clear history flag from the session.
     */
    private function resolveClearHistory(): bool
    {
        return $this->session->get('inertia.clear_history', $this->clearHistory);
    }

    /**
     * Resolve the body of the response, either as JSON or a full view.
     *
     * @param array<string, mixed> $page The complete Inertia page object
     * @return array<string, mixed>|null|InertiaView
     */
    private function resolveBody(array $page): array|null|InertiaView
    {
        if ($this->request->headers->has(Header::INERTIA)) {
            $inertiaVersion = $this->request->headers->get(Header::VERSION);
            $currentVersion = get(ResponseFactory::class)->getVersion();

            if ($this->request->method === Method::GET && $inertiaVersion && $inertiaVersion !== $currentVersion) {
                $this->setStatus(Status::CONFLICT);
                $this->addHeader(Header::LOCATION, $this->request->uri);

                return null;
            }

            $this->addHeader(Header::INERTIA, 'true');
            $this->setContentType(ContentType::JSON);

            return $page;
        }

        $ssr = $this->ssr($page);

        return new InertiaView($this->rootView, ['page' => $page], $ssr?->head, $ssr?->body);
    }

    /**
     * Perform server-side rendering if enabled.
     */
    private function ssr(array $page): ?SsrResponse
    {
        if (!$this->config->ssr->enabled) {
            return null;
        }

        return $this->gateway->dispatch($page);
    }

    /**
     * Get the URL from the request while preserving the trailing slash.
     */
    private function getUrl(): string
    {
        if ($this->urlResolver instanceof Closure) {
            return invoke($this->urlResolver, ['request' => $this->request]);
        }

        $url = $this->request->uri;

        $prefix = $this->request->headers->get(Header::FORWARDED_PREFIX);

        if ($prefix) {
            $prefix = rtrim($prefix, '/');
            $url = $prefix . $url;
        }

        return $url;
    }

    /**
     * Parses an Inertia header that contains a comma-separated list of prop keys.
     *
     * @return string[]
     */
    private function parsePartialHeader(string $name): array
    {
        $headerValue = $this->request->headers->get($name) ?? '';

        if ($headerValue === '') {
            return [];
        }

        return array_filter(explode(',', $headerValue));
    }
}
