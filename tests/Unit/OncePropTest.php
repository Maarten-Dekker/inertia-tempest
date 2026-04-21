<?php

declare(strict_types=1);

namespace Inertia\Tests\Unit;

use Inertia\Props\OnceProp;
use Inertia\Tests\Fixtures\BackedEnum;
use Inertia\Tests\Fixtures\UnitEnum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OncePropTest extends TestCase
{
    #[Test]
    public function can_set_custom_key(): void
    {
        $onceProp = new OnceProp(static fn () => 'value');

        $this->assertSame('custom-key', $onceProp->as('custom-key')->getKey());
        $this->assertSame('foo-value', $onceProp->as(BackedEnum::Foo)->getKey());
        $this->assertSame('Baz', $onceProp->as(UnitEnum::Baz)->getKey());
    }

    #[Test]
    public function should_not_be_refreshed_by_default(): void
    {
        $onceProp = new OnceProp(static fn () => 'value');

        $this->assertFalse($onceProp->shouldBeRefreshed());
    }

    #[Test]
    public function can_forcefully_refresh(): void
    {
        $onceProp = new OnceProp(static fn () => 'value');

        $this->assertTrue($onceProp->fresh()->shouldBeRefreshed());
    }

    #[Test]
    public function can_disable_forceful_refresh(): void
    {
        $onceProp = new OnceProp(static fn () => 'value');

        $this->assertFalse($onceProp->fresh()->fresh(false)->shouldBeRefreshed());
    }
}
