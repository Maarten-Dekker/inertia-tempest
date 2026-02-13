<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\DeferProp;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\Request;

final class DeferPropTest extends TestCase
{
    #[Test]
    public function can_invoke(): void
    {
        $deferProp = new DeferProp(static fn (): string => 'A deferred value', 'default');

        $this->assertSame('A deferred value', $deferProp());
        $this->assertSame('default', $deferProp->group());
    }

    #[Test]
    public function can_invoke_and_merge(): void
    {
        $deferProp = new DeferProp(static fn (): string => 'A deferred value')->merge();

        $this->assertSame('A deferred value', $deferProp());
    }

    #[Test]
    public function can_resolve_bindings_when_invoked(): void
    {
        $deferProp = new DeferProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $deferProp());
    }
}
