<?php

declare(strict_types=1);

use Inertia\Props\MergeProp;
use Inertia\Tests\TestCase;
use Tempest\Http\Request;

class MergePropTest extends TestCase
{
    public function test_can_invoke_with_a_callback(): void
    {
        $mergeProp = new MergeProp(fn(): string => 'A merge prop value');

        $this->assertSame('A merge prop value', $mergeProp());
    }

    public function test_can_invoke_with_a_non_callback(): void
    {
        $mergeProp = new MergeProp(['key' => 'value']);

        $this->assertSame(['key' => 'value'], $mergeProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $mergeProp = new MergeProp(fn(Request $request): Request => $request);

        $this->assertInstanceOf(Request::class, $mergeProp());
    }
}
