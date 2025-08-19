<?php

declare(strict_types=1);

namespace Inertia;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use JsonSerializable;
use Override;

class LazyBody implements JsonSerializable, ArrayAccess
{
    private mixed $builtBody = null;

    private bool $isBuilt = false;

    public function __construct(
        private readonly Closure $builder,
    ) {}

    private function build(): void
    {
        if (!$this->isBuilt) {
            $this->builtBody = ($this->builder)();
            $this->isBuilt = true;
        }
    }

    public function __get(string $name): mixed
    {
        $this->build();

        if (is_object($this->builtBody)) {
            return $this->builtBody->{$name} ?? null;
        }

        return null;
    }

    public function __isset(string $name): bool
    {
        $this->build();

        if (is_object($this->builtBody)) {
            return isset($this->builtBody->{$name});
        }

        return false;
    }

    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        $this->build();

        return isset($this->builtBody[$offset]);
    }

    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        $this->build();

        return $this->builtBody[$offset] ?? null;
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->build();

        $this->builtBody[$offset] = $value;
    }

    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->build();

        unset($this->builtBody[$offset]);
    }

    public function __call(string $method, array $arguments): mixed
    {
        $this->build();

        if (is_object($this->builtBody) && method_exists($this->builtBody, $method)) {
            return $this->builtBody->{$method}(...$arguments);
        }

        $type = get_debug_type($this->builtBody);
        throw new BadMethodCallException("Method {$method} does not exist on type {$type}.");
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        $this->build();

        if ($this->builtBody instanceof JsonSerializable) {
            return $this->builtBody->jsonSerialize();
        }

        return $this->builtBody;
    }
}
