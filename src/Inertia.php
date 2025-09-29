<?php

declare(strict_types=1);

namespace Inertia;

use Closure;
use Deprecated;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferProp;
use Inertia\Props\LazyProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OptionalProp;
use Inertia\Support\Facade;
use Tempest\Http\Responses\Redirect;
use Tempest\Support\Arr\ArrayInterface;

class Inertia extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ResponseFactory::class;
    }

    protected static function instance(): ResponseFactory
    {
        /** @var ResponseFactory $instance */
        $instance = static::getFacadeRoot();
        return $instance;
    }

    public static function setRootView(string $name): void
    {
        static::instance()->setRootView($name);
    }

    /**
     * @param array<array-key, mixed>|\Tempest\Support\Arr\ArrayInterface<array-key, mixed>|\Inertia\Contracts\ProvidesInertiaProperties $key
     */
    public static function share(string|array|ArrayInterface $key, mixed $value = null): void
    {
        static::instance()->share($key, $value);
    }

    public static function getShared(?string $key = null, mixed $default = null): mixed
    {
        return static::instance()->getShared($key, $default);
    }

    public static function clearHistory(): void
    {
        static::instance()->clearHistory();
    }

    public static function encryptHistory(bool $encrypt = true): void
    {
        static::instance()->encryptHistory($encrypt);
    }

    public static function flushShared(): void
    {
        static::instance()->flushShared();
    }

    public static function version(Closure|string|null $version): void
    {
        static::instance()->version($version);
    }

    public static function getVersion(): string
    {
        return static::instance()->getVersion();
    }

    public static function resolveUrlUsing(?Closure $urlResolver = null): void
    {
        static::instance()->resolveUrlUsing($urlResolver);
    }

    public static function optional(callable $callback): OptionalProp
    {
        return static::instance()->optional($callback);
    }

    #[Deprecated(message: 'Use `optional` instead.')]
    public static function lazy(callable $callback): LazyProp
    {
        return static::instance()->lazy($callback);
    }

    public static function defer(callable $callback, string $group = 'default'): DeferProp
    {
        return static::instance()->defer($callback, $group);
    }

    public static function always(mixed $value): AlwaysProp
    {
        return static::instance()->always($value);
    }

    public static function merge(mixed $value): MergeProp
    {
        return static::instance()->merge($value);
    }

    public static function deepMerge(mixed $value): MergeProp
    {
        return static::instance()->deepMerge($value);
    }

    /**
     * @param array<array-key, mixed>|\Tempest\Support\Arr\ArrayInterface<array-key, mixed>|\Inertia\Contracts\ProvidesInertiaProperties $props
     */
    public static function render(string $component, array|ArrayInterface $props = []): Response
    {
        return static::instance()->render($component, $props);
    }

    public static function location(string|Redirect $url): \Tempest\Http\Response
    {
        return static::instance()->location($url);
    }
}
