<?php

declare(strict_types=1);

use Inertia\Props\LazyProp;
use Inertia\Tests\TestCase;
use Tempest\Http\Request;

class LazyPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $lazyProp = new LazyProp(fn(): string => 'A lazy value');

        $this->assertSame('A lazy value', $lazyProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $lazyProp = new LazyProp(fn(Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $lazyProp());
    }
}
