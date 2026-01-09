<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\OptionalProp;
use Inertia\Tests\TestCase;
use Tempest\Http\Request;

final class OptionalPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $optionalProp = new OptionalProp(static fn (): string => 'An optional value');

        $this->assertSame('An optional value', $optionalProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $optionalProp = new OptionalProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $optionalProp());
    }
}
