<?php

declare(strict_types=1);

namespace Inertia\Tests\Unit;

use Inertia\Props\AlwaysProp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AlwaysPropTest extends TestCase
{
    #[Test]
    public function can_accept_scalar_values(): void
    {
        $alwaysProp = new AlwaysProp('An always value');

        $this->assertSame('An always value', $alwaysProp());
    }

    #[Test]
    public function string_function_names_are_not_invoked(): void
    {
        $alwaysProp = new AlwaysProp('date');

        $this->assertSame('date', $alwaysProp());
    }

    #[Test]
    public function can_accept_callables(): void
    {
        $callable = new class {
            public function __invoke(): string
            {
                return 'An always value';
            }
        };

        $alwaysProp = new AlwaysProp($callable);

        $this->assertSame('An always value', $alwaysProp());
    }
}
