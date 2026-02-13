<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\OptionalProp;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\Request;

final class OptionalPropTest extends TestCase
{
    #[Test]
    public function can_invoke(): void
    {
        $optionalProp = new OptionalProp(static fn (): string => 'A lazy value');

        $this->assertSame('A lazy value', $optionalProp());
    }

    #[Test]
    public function can_resolve_bindings_when_invoked(): void
    {
        $optionalProp = new OptionalProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $optionalProp());
    }
}
