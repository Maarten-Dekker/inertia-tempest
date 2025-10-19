<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Contracts\ProvidesScrollMetadata;
use Inertia\Props\ScrollProp;
use Inertia\Support\Header;
use Inertia\Tests\Fixtures\CreateUserTable;
use Inertia\Tests\Fixtures\User;
use Inertia\Tests\Fixtures\UserSeeder;
use Inertia\Tests\TestCase;
use Tempest\Support\Paginator\PaginatedData;

class ScrollPropTest extends TestCase
{
    private PaginatedData $users;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->database->setup();

        $seeder = $this->container->get(UserSeeder::class);
        $this->assertInstanceOf(UserSeeder::class, $seeder);
        $seeder->run(null);

        $this->users = User::select()->paginate(15);
    }

    #[\Override]
    protected function migrateDatabase(): void
    {
        $this->database->migrate(CreateUserTable::class);
    }

    public function test_resolves_meta_data(): void
    {
        $scrollProp = new ScrollProp($this->users);

        $metadata = $scrollProp->metadata();

        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
            ],
            $metadata,
        );
    }

    public function test_resolves_custom_meta_data(): void
    {
        $callableMetadata = fn() => new readonly class('usersPage', 10, 12, 11) implements ProvidesScrollMetadata {
            public function __construct(
                public string $pageName,
                public int|null|string $previousPage,
                public int|null|string $nextPage,
                public int|null|string $currentPage,
            ) {}
        };

        $scrollProp = new ScrollProp(
            value: $this->users,
            wrapper: 'data',
            metadata: $callableMetadata,
        );

        $metadata = $scrollProp->metadata();

        $this->assertSame(
            [
                'pageName' => 'usersPage',
                'previousPage' => 10,
                'nextPage' => 12,
                'currentPage' => 11,
            ],
            $metadata,
        );
    }

    public function test_can_set_the_merge_intent_based_on_the_merge_intent_header(): void
    {
        $this->makeRequest();
        $appendProp = new ScrollProp($this->users);
        $appendProp->configureMergeIntent();

        $this->assertSame(['data'], $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        $this->makeRequest(headers: [
            Header::INFINITE_SCROLL_MERGE_INTENT => 'append',
        ]);
        $appendProp = new ScrollProp($this->users);
        $appendProp->configureMergeIntent();

        $this->assertSame(['data'], $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        $this->makeRequest(headers: [
            Header::INFINITE_SCROLL_MERGE_INTENT => 'prepend',
        ]);
        $prependProp = new ScrollProp($this->users);
        $prependProp->configureMergeIntent();

        $this->assertSame(['data'], $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());

        $this->makeRequest(headers: [
            Header::INFINITE_SCROLL_MERGE_INTENT => 'prepend',
        ]);
        $prependProp = new ScrollProp(
            value: $this->users,
            wrapper: 'items',
        );
        $prependProp->configureMergeIntent();

        $this->assertSame(['items'], $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());
    }

    public function test_resolves_meta_data_with_callable_provider(): void
    {
        $callableMetadata = fn() => new readonly class('callablePage', 5, 7, 6) implements ProvidesScrollMetadata {
            public function __construct(
                public string $pageName,
                public int|null|string $previousPage,
                public int|null|string $nextPage,
                public int|null|string $currentPage,
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

    public function test_scroll_prop_value_is_resolved_only_once(): void
    {
        $callCount = 0;
        $expectedValue = ['item1', 'item2', 'item3'];

        $scrollProp = new ScrollProp(value: function () use (&$callCount, $expectedValue): array {
            $callCount++;

            return $expectedValue;
        });

        $value1 = $scrollProp();
        $value2 = $scrollProp();
        $value3 = $scrollProp();

        $this->assertSame(1, $callCount, 'Scroll prop value callback should only be executed once');

        $this->assertSame($expectedValue, $value1);
        $this->assertSame($value1, $value2);
        $this->assertSame($value2, $value3);
    }
}
