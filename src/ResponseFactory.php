<?php

declare(strict_types=1);

namespace Inertia;

use Closure;
use Deprecated;
use Inertia\Configs\InertiaConfig;
use Inertia\Contracts\ProvidesInertiaProperties;
use Inertia\Exceptions\ComponentNotFoundException;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferProp;
use Inertia\Props\LazyProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OptionalProp;
use Inertia\Support\Header;
use Tempest\Container\Singleton;
use Tempest\Http\GenericResponse;
use Tempest\Http\Request;
use Tempest\Http\Responses\Redirect;
use Tempest\Http\Session\Session;
use Tempest\Http\Status;
use Tempest\Support\Arr;
use Tempest\Support\Arr\ArrayInterface;

use function Tempest\get;
use function Tempest\invoke;

#[Singleton]
final class ResponseFactory
{
    /**
     * @var array<string, bool>
     */
    private array $componentCache = [];

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

    private Session $session {
        get => get(Session::class);
    }

    private Request $request {
        get => get(Request::class);
    }

    private InertiaConfig $config {
        get => get(InertiaConfig::class);
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
     * @param  string|array<array-key, mixed>|\Tempest\Support\Arr\ArrayInterface<array-key, mixed>  $key
     */
    public function share(string|array|ArrayInterface|ProvidesInertiaProperties $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } elseif ($key instanceof ArrayInterface) {
            $this->sharedProps = Arr\merge($this->sharedProps, $key->toArray());
        } elseif ($key instanceof ProvidesInertiaProperties) {
            $this->sharedProps = array_merge($this->sharedProps, [$key]);
        } else {
            $this->sharedProps = Arr\set_by_key($this->sharedProps, $key, $value);
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
            $value = Arr\get_by_key($this->sharedProps, $key, $default);

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
        $version = $this->version instanceof Closure ? invoke($this->version) : $this->version;

        return (string) $version;
    }

    /**
     * Set the URL resolver.
     */
    public function resolveUrlUsing(?Closure $urlResolver = null): void
    {
        $this->urlResolver = $urlResolver;
    }

    /**
     * Clear the browser history on the next visit.
     */
    public function clearHistory(): void
    {
        $this->session->set('inertia.clear_history', true);
    }

    /**
     * Encrypt the browser history.
     */
    public function encryptHistory(bool $encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    /**
     * Create a lazy property.
     */
    #[Deprecated(message: 'Use `optional` instead.')]
    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
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
    public function defer(callable $callback, string $group = 'default'): DeferProp
    {
        return new DeferProp($callback, $group);
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
     * Create an Inertia response.
     *
     * @param  array<array-key, mixed>|\Tempest\Support\Arr\ArrayInterface<array-key, mixed>  $props
     */
    public function render(string $component, array|ArrayInterface|ProvidesInertiaProperties $props = []): Response
    {
        if ($this->config->pages->ensure_pages_exists) {
            $this->findComponentOrFail($component);
        }

        if ($props instanceof ArrayInterface) {
            $props = $props->toArray();
        } elseif ($props instanceof ProvidesInertiaProperties) {
            $props = [$props];
        }

        $combinedProps = array_merge($this->sharedProps, $props);

        return new Response(
            $component,
            $combinedProps,
            $this->rootView,
            $this->getVersion(),
            $this->clearHistory,
            $this->encryptHistory ?? $this->config->history->encrypt,
            $this->urlResolver,
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
     * Find the component or fail.
     *
     * @throws \Inertia\Exceptions\ComponentNotFoundException
     */
    private function findComponentOrFail(string $component): void
    {
        if (isset($this->componentCache[$component])) {
            if (!$this->componentCache[$component]) {
                throw new ComponentNotFoundException($component, $this->config->pages->page_paths);
            }

            return;
        }

        $componentPath = str_replace('/', DIRECTORY_SEPARATOR, $component);
        $paths = $this->config->pages->page_paths;
        $extensions = $this->config->pages->page_extensions;

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
}
