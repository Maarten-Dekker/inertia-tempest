<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\LazyProp;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\Request;

final class LazyPropTest extends TestCase
{
    #[Test]
    public function can_invoke(): void
    {
        $lazyProp = new LazyProp(static fn (): string => 'A lazy value');

        $this->assertSame('A lazy value', $lazyProp());
    }

    #[Test]
    public function can_resolve_bindings_when_invoked(): void
    {
        $lazyProp = new LazyProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $lazyProp());
    }
}
