<?php

declare(strict_types=1);

namespace Inertia\Tests\Unit;

use Inertia\Props\LazyProp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LazyPropTest extends TestCase
{
    #[Test]
    public function string_function_names_are_not_invoked(): void
    {
        $lazyProp = new LazyProp('date');

        $this->assertSame('date', $lazyProp());
    }
}
