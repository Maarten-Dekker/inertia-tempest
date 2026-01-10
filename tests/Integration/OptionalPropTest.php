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
        $optionalProp = new OptionalProp(static fn (): string => 'A lazy value');

        $this->assertSame('A lazy value', $optionalProp());
    }

    public function test_string_function_names_are_not_invoked(): void
    {
        $optionalProp = new OptionalProp('date');

        $this->assertSame('date', $optionalProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $optionalProp = new OptionalProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $optionalProp());
    }
}
