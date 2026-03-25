<?php

declare(strict_types=1);

namespace Inertia;

use BackedEnum;
use Closure;
use DateInterval;
use DateTimeImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Inertia\Configs\InertiaConfig;
use Inertia\Contracts\Arrayable;
use Inertia\Contracts\Deferrable;
use Inertia\Contracts\IgnoreFirstLoad;
use Inertia\Contracts\InvokableProp;
use Inertia\Contracts\Mergeable;
use Inertia\Contracts\Onceable;
use Inertia\Contracts\ProvidesInertiaProperties;
use Inertia\Contracts\ProvidesInertiaProperty;
use Inertia\Props\AlwaysProp;
use Inertia\Props\ScrollProp;
use Inertia\Ssr\Contracts\Gateway;
use Inertia\Ssr\Response as SsrResponse;
use Inertia\Support\Header;
use Inertia\Support\PaginatorAdapter;
use Inertia\Support\PropertyContext;
use Inertia\Support\RenderContext;
use Inertia\Support\SessionKey;
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
use UnitEnum;

use function Inertia\Support\Arr\forget_keys;
use function Tempest\Container\get;
use function Tempest\Container\invoke;
use function Tempest\Support\Arr\get_by_key;
use function Tempest\Support\Arr\set_by_key;
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
        get => $this->request ??= get(Request::class);
    }

    private InertiaConfig $config {
        get => $this->config ??= get(InertiaConfig::class);
    }

    private Gateway $gateway {
        get => $this->gateway ??= get(Gateway::class);
    }

    /**
     * Create a new Inertia response instance.
     *
     * @param array<array-key, mixed|ProvidesInertiaProperties> $props
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
        $this->body = new LazyBody(function (): array|null|InertiaView {
            $this->props = $this->normalizeProps($this->props);
            $this->props = $this->resolveInertiaPropsProviders($this->props);

            $resolvedProps = $this->resolveProperties($this->props);

            $page = array_merge(
                [
                    'component' => $this->component,
                    'props' => $resolvedProps,
                    'url' => $this->getUrl(),
                    'version' => $this->version,
                    'clearHistory' => $this->session->consume(SessionKey::ClearHistory->value, $this->clearHistory),
                    'encryptHistory' => $this->encryptHistory,
                ],
                $this->resolveMergeProps(),
                $this->resolveDeferredProps(),
                $this->resolveCacheDirections(),
                $this->resolveScrollProps(),
                $this->resolveOnceProps(),
                $this->resolveFlashData(),
            );

            return $this->resolveBody($page);
        });
    }

    /**
     * Add additional properties to the page.
     *
     * @param string|array<string, mixed>|ProvidesInertiaProperties $key
     */
    public function with(string|array|ProvidesInertiaProperties $key, mixed $value = null): self
    {
        if ($key instanceof ProvidesInertiaProperties) {
            $this->props[] = $key;
            return $this;
        }

        $data = is_array($key) ? $key : [$key => $value];
        $this->props = array_merge($this->props, $data);

        return $this;
    }

    /**
     * Add additional data to the view.
     */
    public function withViewData(string|array $key, mixed $value = null): self
    {
        $data = is_array($key) ? $key : [$key => $value];

        $this->viewData = array_merge($this->viewData, $data);

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
     * @param string|array<int, mixed> $cacheFor
     */
    public function cache(string|array $cacheFor): self
    {
        $this->cacheFor = is_array($cacheFor) ? $cacheFor : [$cacheFor];

        return $this;
    }

    /**
     * Add flash data to the response.
     *
     * @param BackedEnum|UnitEnum|string|array<string, mixed> $key
     */
    public function flash(BackedEnum|UnitEnum|string|array $key, mixed $value = null): self
    {
        inertia()->flash($key, $value);

        return $this;
    }

    /**
     * Resolve the properties for the response.
     *
     * @param array<array-key, mixed> $props
     * @return array<string, mixed>
     */
    public function resolveProperties(array $props): array
    {
        $props = $this->resolvePartialProperties($props);
        $props = $this->resolveOnceProperties($props);
        $props = $this->resolveAlways($props);

        return $this->resolvePropertyInstances($props);
    }

    /**
     * Resolve the ProvidesInertiaProperties props.
     *
     * @param array<array-key, mixed> $props
     * @return array<string, mixed>
     */
    public function resolveInertiaPropsProviders(array $props): array
    {
        $newProps = [];

        $renderContext = new RenderContext($this->component);

        foreach ($props as $key => $value) {
            if (is_numeric($key) && $value instanceof ProvidesInertiaProperties) {
                /** @var array<string, mixed> $inertiaProps */
                $inertiaProps = to_array($value->toInertiaProperties($renderContext));
                $newProps = array_merge($newProps, $inertiaProps);
                continue;
            }

            $newProps[$key] = $value;
        }

        return $newProps;
    }

    /**
     * Resolve properties for partial requests. Filters properties based on
     * 'only' and 'except' headers from the client, allowing for selective
     * data loading to improve performance.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public function resolvePartialProperties(array $props): array
    {
        if (! $this->isPartial()) {
            return array_filter(
                $props,
                static fn ($prop) => (
                    ! $prop instanceof IgnoreFirstLoad && ! ($prop instanceof Deferrable && $prop->shouldDefer())
                ),
            );
        }

        $only = $this->parseHeaderAsArray(Header::PARTIAL_ONLY);
        $except = $this->parseHeaderAsArray(Header::PARTIAL_EXCEPT);

        if ($only !== []) {
            $newProps = [];

            foreach ($only as $key) {
                $value = get_by_key($props, $key);

                if ($value instanceof ArrayInterface) {
                    $value = $value->toArray();
                }

                $newProps = set_by_key($newProps, $key, $value);
            }

            $props = $newProps;
        }

        if ($except !== []) {
            forget_keys($props, $except);
        }

        return $props;
    }

    /**
     *  Resolve properties that should only be resolved once.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public function resolveOnceProperties(array $props): array
    {
        $isInertia = (bool) $this->request->headers->get(Header::INERTIA);
        $isPartial = $this->isPartial();

        if (! $isInertia || $isPartial) {
            return $props;
        }

        $exceptOnceProps = $this->parseHeaderAsArray(Header::EXCEPT_ONCE_PROPS);

        if ($exceptOnceProps === []) {
            return $props;
        }

        return array_filter(
            $props,
            fn (mixed $prop, string $key) => ! $this->isExcludedOnce($prop, $key, $exceptOnceProps),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Resolve `always` properties that should always be included.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public function resolveAlways(array $props): array
    {
        $always = array_filter($this->props, static fn ($prop) => $prop instanceof AlwaysProp);

        return array_merge($always, $props);
    }

    /**
     * Resolve all necessary class instances in the given props.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public function resolvePropertyInstances(
        array $props,
        bool $unpackDotProps = true,
        ?string $parentKey = null,
    ): array {
        $result = [];

        foreach ($props as $key => $value) {
            if ($value instanceof ScrollProp) {
                $value->configureMergeIntent();
            }

            $value = match (true) {
                $value instanceof Closure => invoke($value),
                $value instanceof InvokableProp => $value(),
                default => $value,
            };

            if ($this->config->laravel_pagination && $value instanceof PaginatedData) {
                $value = new PaginatorAdapter(
                    paginator: $value,
                    request: $this->request,
                );
            }

            $currentKey = $parentKey ? $parentKey . '.' . $key : $key;

            if ($value instanceof ProvidesInertiaProperty) {
                $value = $value->toInertiaProperty(new PropertyContext(
                    key: $currentKey,
                    props: $props,
                ));
            }

            $value = match (true) {
                $value instanceof Arrayable, $value instanceof ArrayInterface => $value->toArray(),
                $value instanceof PromiseInterface => $value->wait(),
                $value instanceof HttpResponse => $value->body,
                default => $value,
            };

            if (is_array($value)) {
                $value = $this->resolvePropertyInstances(
                    props: $value,
                    unpackDotProps: false,
                    parentKey: $currentKey,
                );
            }

            if ($unpackDotProps && is_string($key) && str_contains($key, '.')) {
                $result = set_by_key($result, $key, $value);
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
            'cache' => array_map(static function ($value): int {
                if ($value instanceof DateInterval) {
                    return new DateTimeImmutable('@0')->add($value)->getTimestamp();
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
    public function getMergePropsForRequest(bool $rejectResetProps = true): array
    {
        $props = array_filter($this->props, static fn ($prop) => $prop instanceof Mergeable && $prop->shouldMerge());

        if ($rejectResetProps) {
            $resetProps = $this->parseHeaderAsArray(Header::RESET);
            if ($resetProps !== []) {
                $props = array_diff_key($props, array_flip($resetProps));
            }
        }

        $onlyProps = $this->parseHeaderAsArray(Header::PARTIAL_ONLY);
        if ($onlyProps !== []) {
            $props = array_intersect_key($props, array_flip($onlyProps));
        }

        $exceptProps = $this->parseHeaderAsArray(Header::PARTIAL_EXCEPT);
        if ($exceptProps !== []) {
            return array_diff_key($props, array_flip($exceptProps));
        }

        return $props;
    }

    /**
     * Resolve merge props configuration for client-side prop merging.
     *
     * @return array<string, mixed>
     */
    public function resolveMergeProps(): array
    {
        $mergeableProps = $this->getMergePropsForRequest();

        $appendProps = [];
        $prependProps = [];
        $deepMergeProps = [];
        $matchPropsOn = [];

        foreach ($mergeableProps as $key => $prop) {
            if ($prop->shouldDeepMerge()) {
                $deepMergeProps[] = $key;
            }

            if (! $prop->shouldDeepMerge()) {
                if ($prop->appendsAtRoot()) {
                    $appendProps[] = $key;
                } else {
                    foreach ($prop->appendsAtPaths() as $path) {
                        $appendProps[] = "{$key}.{$path}";
                    }
                }

                if ($prop->prependsAtRoot()) {
                    $prependProps[] = $key;
                } else {
                    foreach ($prop->prependsAtPaths() as $path) {
                        $prependProps[] = "{$key}.{$path}";
                    }
                }
            }

            foreach ($prop->matchesOn() as $strategy) {
                $matchPropsOn[] = "{$key}.{$strategy}";
            }
        }

        return array_filter(
            [
                'mergeProps' => array_values(array_unique($appendProps)),
                'prependProps' => array_values(array_unique($prependProps)),
                'deepMergeProps' => $deepMergeProps,
                'matchPropsOn' => array_values(array_unique($matchPropsOn)),
            ],
            static fn (array $prop) => $prop !== [],
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

        $exceptOnceProps = $this->parseHeaderAsArray(Header::EXCEPT_ONCE_PROPS);

        $groupedProps = [];
        foreach ($this->props as $key => $prop) {
            if (! $prop instanceof Deferrable) {
                continue;
            }

            if (! $prop->shouldDefer()) {
                continue;
            }

            if ($this->isExcludedOnce($prop, $key, $exceptOnceProps)) {
                continue;
            }

            $group = $prop->group();
            $groupedProps[$group][] = $key;
        }

        return $groupedProps === [] ? [] : ['deferredProps' => $groupedProps];
    }

    /**
     * Resolve scroll props configuration for client-side infinite scrolling.
     *
     * @return array<string, mixed>
     */
    public function resolveScrollProps(): array
    {
        $resetProps = $this->parseHeaderAsArray(Header::RESET);
        $isPartial = $this->isPartial();

        $scrollPropsResult = [];
        foreach ($this->getMergePropsForRequest(false) as $key => $prop) {
            if (! $prop instanceof ScrollProp) {
                continue;
            }

            if (! $isPartial && $prop->shouldDefer()) {
                continue;
            }

            $scrollPropsResult[$key] = [
                ...$prop->metadata(),
                'reset' => in_array($key, $resetProps, true),
            ];
        }

        return $scrollPropsResult === [] ? [] : ['scrollProps' => $scrollPropsResult];
    }

    /**
     * Resolve props that should only be resolved once.
     *
     * @return array<string, array<int, string>>
     */
    public function resolveOnceProps(): array
    {
        $props = array_filter(
            $this->props,
            static fn ($prop) => $prop instanceof Onceable && $prop->shouldResolveOnce(),
        );

        $only = $this->parseHeaderAsArray(Header::PARTIAL_ONLY);
        if ($only !== []) {
            $props = array_intersect_key($props, array_flip($only));
        }

        $except = $this->parseHeaderAsArray(Header::PARTIAL_EXCEPT);
        if ($except !== []) {
            $props = array_diff_key($props, array_flip($except));
        }

        $mapped = [];
        foreach ($props as $key => $prop) {
            /** @var Onceable $prop */
            $mapped[$prop->getKey() ?? $key] = [
                'prop' => $key,
                'expiresAt' => $prop->expiresAt(),
            ];
        }

        return $mapped !== [] ? ['onceProps' => $mapped] : [];
    }

    /**
     * Resolve flash data from the session.
     *
     * @return array<string, mixed>
     */
    private function resolveFlashData(): array
    {
        $flash = inertia()->getFlashed();

        return $flash !== [] ? ['flash' => $flash] : [];
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
        return $props instanceof ArrayInterface ? $props->toArray() : $props;
    }

    /**
     * Determines if a prop should be excluded because it has already been resolved once.
     */
    private function isExcludedOnce(mixed $prop, string $key, array $exceptOnceProps): bool
    {
        if (! $prop instanceof Onceable) {
            return false;
        }

        if (! $prop->shouldResolveOnce()) {
            return false;
        }

        if ($prop->shouldBeRefreshed()) {
            return false;
        }

        return in_array($prop->getKey() ?? $key, $exceptOnceProps, true);
    }

    /**
     * Resolve the body of the response, either as JSON or a full view.
     *
     * @param array<string, mixed> $page The complete Inertia page object
     * @return array<string, mixed>|null|InertiaView
     */
    private function resolveBody(array $page): array|null|InertiaView
    {
        if (! $this->request->headers->has(Header::INERTIA)) {
            $ssr = $this->ssr($page);

            return new InertiaView(
                path: $this->rootView,
                inertia: ['page' => $page] + $this->viewData,
                ssrHead: $ssr?->head,
                ssrBody: $ssr?->body,
            );
        }

        $inertiaVersion = $this->request->headers->get(Header::VERSION);

        if (
            $this->request->method === Method::GET
            && $inertiaVersion !== null
            && $inertiaVersion !== ''
            && $inertiaVersion !== '0'
            && $inertiaVersion !== $this->version
        ) {
            $this->setStatus(Status::CONFLICT);
            $this->addHeader(Header::LOCATION, $this->request->uri);

            return null;
        }

        $this->addHeader(Header::INERTIA, 'true');
        $this->setContentType(ContentType::JSON);

        return $page;
    }

    /**
     * Perform server-side rendering if enabled.
     */
    private function ssr(array $page): ?SsrResponse
    {
        if (! $this->config->ssr->enabled) {
            return null;
        }

        return $this->gateway->dispatch($page);
    }

    /**
     * Get the URL from the request while preserving the trailing slash.
     */
    private function getUrl(): string
    {
        if ($this->urlResolver) {
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
     * Parses a comma-separated request header into an array of strings.
     *
     * @return string[]
     */
    private function parseHeaderAsArray(string $name): array
    {
        $headerValue = $this->request->headers->get($name) ?? '';

        if ($headerValue === '') {
            return [];
        }

        return array_filter(explode(',', $headerValue));
    }
}
