<?php

declare(strict_types=1);

namespace Inertia\Tests\Unit;

use Inertia\Contracts\ProvidesScrollMetadata;
use Inertia\Props\ScrollProp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScrollPropTest extends TestCase
{
    #[Test]
    public function resolves_meta_data_with_callable_provider(): void
    {
        $callableMetadata = static fn () => new readonly class('callablePage', 5, 7, 6) implements
            ProvidesScrollMetadata {
            public function __construct(
                public string $pageName,
                public int|string|null $previousPage,
                public int|string|null $nextPage,
                public int|string|null $currentPage,
            ) {}
        };

        $scrollProp = new ScrollProp(
            value: [],
            wrapper: 'data',
            metadata: $callableMetadata,
        );

        $metadata = $scrollProp->metadata();

        $this->assertSame(
            [
                'pageName' => 'callablePage',
                'previousPage' => 5,
                'nextPage' => 7,
                'currentPage' => 6,
            ],
            $metadata,
        );
    }

    #[Test]
    public function string_function_names_are_not_invoked(): void
    {
        $scrollProp = new ScrollProp('date');

        $this->assertSame('date', $scrollProp());
    }
}
