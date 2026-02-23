<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\OnceProp;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\Request;

final class OncePropTest extends TestCase
{
    #[Test]
    public function can_invoke_with_a_callback(): void
    {
        $onceProp = new OnceProp(static fn () => 'A once prop value');

        $this->assertSame('A once prop value', $onceProp());
    }

    #[Test]
    public function can_resolve_bindings_when_invoked(): void
    {
        $onceProp = new OnceProp(static fn (Request $request) => $request);

        $this->assertInstanceOf(Request::class, $onceProp());
    }
}
