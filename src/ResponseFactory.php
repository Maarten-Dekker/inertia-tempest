<?php

declare(strict_types=1);

namespace Inertia;

use BackedEnum;
use Closure;
use Inertia\Configs\InertiaConfig;
use Inertia\Contracts\ProvidesInertiaProperties;
use Inertia\Contracts\ProvidesScrollMetadata;
use Inertia\Exceptions\ComponentNotFoundException;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OnceProp;
use Inertia\Props\OptionalProp;
use Inertia\Props\ScrollProp;
use Inertia\Support\Header;
use Inertia\Support\SessionKey;
use InvalidArgumentException;
use Tempest\Container\Singleton;
use Tempest\Http\GenericResponse;
use Tempest\Http\Request;
use Tempest\Http\Responses\Back;
use Tempest\Http\Responses\Redirect;
use Tempest\Http\Session\Session;
use Tempest\Http\Status;
use Tempest\Support\Arr\ArrayInterface;
use UnitEnum;

use function Tempest\Container\get;
use function Tempest\Container\invoke;
use function Tempest\Support\Arr\get_by_key;
use function Tempest\Support\Arr\merge;
use function Tempest\Support\Arr\set_by_key;

#[Singleton]
final class ResponseFactory
{
    /**
     * The name of the root view.
     */
    private string $rootView = 'inertia.view.php';

    /**
     * The shared properties.
     *
     * @var array<string, mixed>
     */
    private array $sharedProps = [];

    /**
     * The asset version.
     */
    private Closure|string|int|float|null $version = null;

    /**
     * Indicates if the browser history should be cleared.
     */
    private bool $clearHistory = false;

    /**
     * Indicates if the browser history should be encrypted.
     */
    private ?bool $encryptHistory = null;

    /**
     * The URL resolver callback.
     */
    private ?Closure $urlResolver = null;

    /**
     * The component transformer callback.
     *
     * @var (Closure(string): string)|null
     */
    private ?Closure $componentTransformer = null;

    /**
     * @var array<string, bool>
     */
    private array $componentCache = [];

    /**
     * Holds flash data accumulated during the current request.
     *
     * @var array<string, mixed>
     */
    private array $pendingFlash = [];

    private Session $session {
        get => $this->session ??= get(Session::class);
    }

    private Request $request {
        get => $this->request ??= get(Request::class);
    }

    private InertiaConfig $config {
        get => $this->config ??= get(InertiaConfig::class);
    }

    /**
     * Set the root view template for Inertia responses. This template
     * serves as the HTML wrapper that contains the Inertia root element
     * where the frontend application will be mounted.
     */
    public function setRootView(string $name): void
    {
        $this->rootView = $name;
    }

    /**
     * Share data across all Inertia responses. This data is automatically
     * included with every response, making it ideal for user authentication
     * state, flash messages, etc.
     *
     * @param string|array<array-key, mixed>|ArrayInterface<array-key, mixed> $key
     */
    public function share(string|array|ArrayInterface|ProvidesInertiaProperties $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } elseif ($key instanceof ArrayInterface) {
            $this->sharedProps = merge($this->sharedProps, $key->toArray());
        } elseif ($key instanceof ProvidesInertiaProperties) {
            $this->sharedProps = array_merge($this->sharedProps, [$key]);
        } else {
            $this->sharedProps = set_by_key($this->sharedProps, $key, $value);
        }
    }

    /**
     * Get the shared data for a given key. Returns all shared data if
     * no key is provided, or the value for a specific key with an
     * optional default fallback.
     */
    public function getShared(?string $key = null, mixed $default = null): mixed
    {
        if ($key) {
            $value = get_by_key($this->sharedProps, $key, $default);

            if ($value instanceof ArrayInterface) {
                return $value->toArray();
            }

            return $value;
        }

        return $this->sharedProps;
    }

    /**
     * Flush all shared data.
     */
    public function flushShared(): void
    {
        $this->sharedProps = [];
    }

    /**
     * Set the asset version.
     */
    public function version(Closure|string|int|float|null $version): void
    {
        $this->version = $version;
    }

    /**
     * Get the asset version.
     */
    public function getVersion(): string
    {
        if ($this->version instanceof Closure) {
            $this->version = (string) invoke($this->version);
        }

        return (string) $this->version;
    }

    /**
     * Set the URL resolver.
     */
    public function resolveUrlUsing(?Closure $urlResolver = null): void
    {
        $this->urlResolver = $urlResolver;
    }

    /**
     * Set the component transformer.
     */
    public function transformComponentUsing(?Closure $componentTransformer = null): void
    {
        $this->componentTransformer = $componentTransformer;
    }

    /**
     * Clear the browser history on the next visit.
     */
    public function clearHistory(): void
    {
        $this->session->set(SessionKey::ClearHistory->value, true);
    }

    /**
     * Encrypt the browser history.
     */
    public function encryptHistory(bool $encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    /**
     * Preserve the URL fragment across the next redirect.
     */
    public function preserveFragment(): void
    {
        $this->session->set(SessionKey::PreserveFragment->value, true);
    }

    /**
     * Create an optional property.
     */
    public function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Create a deferred property.
     */
    public function defer(callable $callback, string $group = 'default', bool $rescue = false): DeferProp
    {
        return new DeferProp(
            callback: $callback,
            group: $group,
            rescue: $rescue,
        );
    }

    /**
     * Create a merge property.
     */
    public function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * Create a deep merge property.
     */
    public function deepMerge(mixed $value): MergeProp
    {
        return new MergeProp($value)->deepMerge();
    }

    /**
     * Create an always property.
     */
    public function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * Create a scroll property.
     */
    public function scroll(
        mixed $value,
        string $pageName = 'page',
        string $wrapper = 'data',
        ProvidesScrollMetadata|callable|null $metadata = null,
    ): ScrollProp {
        return new ScrollProp(
            value: $value,
            pageName: $pageName,
            wrapper: $wrapper,
            metadata: $metadata,
        );
    }

    /**
     * Create a once property.
     */
    public function once(callable $callback): OnceProp
    {
        return new OnceProp($callback);
    }

    /**
     * Create and share a once property.
     */
    public function shareOnce(string $key, callable $callback): OnceProp
    {
        $prop = new OnceProp($callback);

        $this->share(
            key: $key,
            value: $prop,
        );

        return $prop;
    }

    /**
     * Create an Inertia response.
     *
     * @param array<array-key, mixed>|ArrayInterface<array-key, mixed> $props
     * @throws ComponentNotFoundException
     */
    public function render(
        string|BackedEnum|UnitEnum $component,
        array|ArrayInterface|ProvidesInertiaProperties $props = [],
    ): Response {
        $component = match (true) {
            $component instanceof BackedEnum => $component->value,
            $component instanceof UnitEnum => $component->name,
            default => $component,
        };

        if (! is_string($component)) {
            throw new InvalidArgumentException('Component argument must be of type string or a string BackedEnum');
        }

        $component = $this->transformComponent($component);

        if ($this->config->pages->ensure_pages_exist) {
            $this->findComponentOrFail($component);
        }

        if ($props instanceof ArrayInterface) {
            $props = $props->toArray();
        } elseif ($props instanceof ProvidesInertiaProperties) {
            $props = [$props];
        }

        $combinedProps = array_merge($this->sharedProps, $props);

        return new Response(
            component: $component,
            props: $combinedProps,
            rootView: $this->rootView,
            version: $this->getVersion(),
            clearHistory: $this->clearHistory,
            encryptHistory: $this->encryptHistory ?? $this->config->history->encrypt,
            urlResolver: $this->urlResolver,
        );
    }

    /**
     * Create an Inertia location response.
     */
    public function location(string|Redirect $url): GenericResponse|Redirect
    {
        if ($this->request->headers->has(Header::INERTIA)) {
            if ($url instanceof Redirect) {
                $url = $url->getHeader('Location')->values[0];
            }

            return new GenericResponse(
                status: Status::CONFLICT,
                headers: [Header::LOCATION => $url],
            );
        }

        return $url instanceof Redirect ? $url : new Redirect($url);
    }

    /**
     * Flash data to be included with the next response. Unlike regular props,
     * flash data is not persisted in the browser's history state, making it
     * ideal for one-time notifications like toasts or highlights.
     *
     * @param BackedEnum|UnitEnum|string|array<string, mixed> $key
     */
    public function flash(BackedEnum|UnitEnum|string|array $key, mixed $value = null): self
    {
        if (! is_array($key)) {
            $key = match (true) {
                $key instanceof BackedEnum => $key->value,
                $key instanceof UnitEnum => $key->name,
                default => $key,
            };

            $key = [$key => $value];
        }

        $this->pendingFlash = [
            ...$this->pendingFlash,
            ...$key,
        ];

        $this->session->flash(
            key: SessionKey::FlashData->value,
            value: $this->pendingFlash,
        );

        return $this;
    }

    /**
     * Create a new redirect response to the previous location.
     */
    public function back(?string $fallback = null): Back
    {
        return new Back($fallback);
    }

    /**
     * Retrieve the flashed data from the session.
     *
     * @return array<string, mixed>
     */
    public function getFlashed(): array
    {
        return $this->session->get(SessionKey::FlashData->value) ?? [];
    }

    /**
     * Find the component or fail.
     *
     * @throws ComponentNotFoundException
     */
    private function findComponentOrFail(string $component): void
    {
        if (isset($this->componentCache[$component])) {
            if (! $this->componentCache[$component]) {
                throw new ComponentNotFoundException($component, $this->config->pages->paths);
            }

            return;
        }

        $componentPath = str_replace('/', DIRECTORY_SEPARATOR, $component);
        $paths = $this->config->pages->paths;
        $extensions = $this->config->pages->extensions;

        foreach ($paths as $path) {
            foreach ($extensions as $extension) {
                $ext = str_starts_with((string) $extension, '.') ? $extension : '.' . $extension;

                if (file_exists($path . DIRECTORY_SEPARATOR . $componentPath . $ext)) {
                    $this->componentCache[$component] = true;
                    return;
                }
            }
        }

        $this->componentCache[$component] = false;
        throw new ComponentNotFoundException($component, $paths);
    }

    /**
     * Transform the component name.
     */
    private function transformComponent(string $component): string
    {
        if (! $this->componentTransformer) {
            return $component;
        }

        return ($this->componentTransformer)($component) ?? $component;
    }
}
