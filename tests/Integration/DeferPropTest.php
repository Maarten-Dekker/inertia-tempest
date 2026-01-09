<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\DeferProp;
use Inertia\Tests\TestCase;
use Tempest\Http\Request;

final class DeferPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $deferProp = new DeferProp(static fn (): string => 'A deferred value', 'default');

        $this->assertSame('A deferred value', $deferProp());
        $this->assertSame('default', $deferProp->group());
    }

    public function test_string_function_names_are_not_invoked(): void
    {
        $deferProp = new DeferProp('date');

        $this->assertSame('date', $deferProp());
    }

    public function test_can_invoke_and_merge(): void
    {
        $deferProp = new DeferProp(static fn (): string => 'A deferred value')->merge();

        $this->assertSame('A deferred value', $deferProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $deferProp = new DeferProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $deferProp());
    }
}
