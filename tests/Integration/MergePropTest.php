<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Props\MergeProp;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\Request;

final class MergePropTest extends TestCase
{
    #[Test]
    public function can_invoke_with_a_callback(): void
    {
        $mergeProp = new MergeProp(static fn (): string => 'A merge prop value');

        $this->assertSame('A merge prop value', $mergeProp());
    }

    #[Test]
    public function can_resolve_bindings_when_invoked(): void
    {
        $mergeProp = new MergeProp(static fn (Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $mergeProp());
    }
}
