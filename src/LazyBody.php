<?php

declare(strict_types=1);

namespace Inertia;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use JsonSerializable;
use Override;

final class LazyBody implements JsonSerializable, ArrayAccess
{
    private mixed $body {
        get => $this->body ??= ($this->builder)();
        set => $this->body = $value;
    }

    public function __construct(
        private readonly Closure $builder,
    ) {}

    public function __get(string $name): mixed
    {
        return $this->body?->{$name};
    }

    public function __isset(string $name): bool
    {
        return isset($this->body->{$name});
    }

    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->body[$offset]);
    }

    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->body[$offset] ?? null;
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $body = $this->body;

        if (is_null($offset)) {
            $body[] = $value;
        } else {
            $body[$offset] = $value;
        }

        $this->body = $body;
    }

    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $body = $this->body;

        unset($body[$offset]);

        $this->body = $body;
    }

    public function __call(string $method, array $arguments): mixed
    {
        if (is_object($this->body) && method_exists($this->body, $method)) {
            return $this->body->{$method}(...$arguments);
        }

        $type = get_debug_type($this->body);
        throw new BadMethodCallException("Method {$method} does not exist on type {$type}.");
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->body;
    }
}
