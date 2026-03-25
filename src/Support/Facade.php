<?php

declare(strict_types=1);

namespace Inertia\Support;

use RuntimeException;

use function Tempest\Container\get;

abstract class Facade
{
    protected static array $resolvedInstance = [];

    protected static function resolveFacadeInstance(string $name): mixed
    {
        return static::$resolvedInstance[$name] ??= get($name);
    }

    protected static function getFacadeRoot(): mixed
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return $instance->{$method}(...$args);
    }
}
