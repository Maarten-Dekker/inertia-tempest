<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\AlwaysProp;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\Request;

final class AlwaysPropTest extends TestCase
{
    #[Test]
    public function can_invoke(): void
    {
        $alwaysProp = new AlwaysProp(static fn (): string => 'An always value');

        $this->assertSame('An always value', $alwaysProp());
    }

    #[Test]
    public function can_resolve_bindings_when_invoked(): void
    {
        $alwaysProp = new AlwaysProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $alwaysProp());
    }
}
