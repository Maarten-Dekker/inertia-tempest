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
    public function test_can_set_custom_key(): void
    {
        $onceProp = new OnceProp(static fn () => 'value');

        $result = $onceProp->as('custom-key');
        $this->assertSame($onceProp, $result);
        $this->assertSame('custom-key', $onceProp->getKey());

        $onceProp->as(BackedEnum::Foo);
        $this->assertSame('foo-value', $onceProp->getKey());

        $onceProp->as(UnitEnum::Baz);
        $this->assertSame('Baz', $onceProp->getKey());
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
        $onceProp->fresh();

        $this->assertTrue($onceProp->shouldBeRefreshed());
    }

    #[Test]
    public function can_disable_forceful_refresh(): void
    {
        $onceProp = new OnceProp(static fn () => 'value');
        $onceProp->fresh();
        $onceProp->fresh(false);

        $this->assertFalse($onceProp->shouldBeRefreshed());
    }
}
