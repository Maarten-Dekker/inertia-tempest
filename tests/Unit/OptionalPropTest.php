<?php

declare(strict_types=1);

namespace Inertia\Tests\Unit;

use Inertia\Props\OptionalProp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OptionalPropTest extends TestCase
{
    #[Test]
    public function string_function_names_are_not_invoked(): void
    {
        $optionalProp = new OptionalProp('date');

        $this->assertSame('date', $optionalProp());
    }
}
