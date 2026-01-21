<?php

declare(strict_types=1);

namespace Inertia;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use JsonSerializable;
use LogicException;
use Override;

final class LazyBody implements JsonSerializable, ArrayAccess
{
    private mixed $body {
        get => $this->body ??= ($this->builder)();
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
        throw new LogicException(
            'Inertia LazyBody is immutable. You cannot modify props after the Response is created.',
        );
    }

    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Inertia LazyBody is immutable.');
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
