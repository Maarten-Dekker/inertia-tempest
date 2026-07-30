<?php

declare(strict_types=1);

namespace Inertia\Tests\Unit;

use Inertia\Props\DeferProp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeferPropTest extends TestCase
{
    #[Test]
    public function string_function_names_are_not_invoked(): void
    {
        $deferProp = new DeferProp('date');

        $this->assertSame('date', $deferProp());
    }

    #[Test]
    public function can_be_marked_as_rescuable(): void
    {
        $deferProp = new DeferProp(static fn () => 'value', rescue: true);

        $this->assertTrue($deferProp->shouldRescue());
    }
}
