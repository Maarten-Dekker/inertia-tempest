<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\LazyProp;
use Inertia\Tests\TestCase;
use Tempest\Http\Request;

final class LazyPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $lazyProp = new LazyProp(static fn (): string => 'A lazy value');

        $this->assertSame('A lazy value', $lazyProp());
    }

    public function test_string_function_names_are_not_invoked(): void
    {
        $lazyProp = new LazyProp('date');

        $this->assertSame('date', $lazyProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $lazyProp = new LazyProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $lazyProp());
    }
}
