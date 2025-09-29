<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\AlwaysProp;
use Inertia\Tests\TestCase;
use Tempest\Http\Request;

final class AlwaysPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $alwaysProp = new AlwaysProp(fn(): string => 'An always value');

        $this->assertSame('An always value', $alwaysProp());
    }

    public function test_can_accept_scalar_values(): void
    {
        $alwaysProp = new AlwaysProp('An always value');

        $this->assertSame('An always value', $alwaysProp());
    }

    public function test_can_accept_callables(): void
    {
        $callable = new class {
            public function __invoke(): string
            {
                return 'An always value';
            }
        };

        $alwaysProp = new AlwaysProp($callable);

        $this->assertSame('An always value', $alwaysProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $alwaysProp = new AlwaysProp(fn(Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $alwaysProp());
    }
}
