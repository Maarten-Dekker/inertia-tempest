<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Contracts\ProvidesScrollMetadata;
use Inertia\Props\ScrollProp;
use Inertia\Response;
use Inertia\Support\Header;
use Inertia\Tests\Fixtures\User;
use Inertia\Tests\Fixtures\UserSeeder;
use Inertia\Tests\TestCase;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Support\Paginator\PaginatedData;

final class ScrollPropTest extends TestCase
{
    private static bool $dbInitialized = false;

    private PaginatedData $users;

    #[PreCondition]
    protected function configure(): void
    {
        if (! self::$dbInitialized) {
            $this->database->setup();
            new UserSeeder()->run(null);
            self::$dbInitialized = true;
        }

        $this->users = User::select()->paginate(15);
    }

    #[Test]
    public function resolves_meta_data(): void
    {
        $scrollProp = new ScrollProp($this->users);

        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
            ],
            $scrollProp->metadata(),
        );
    }

    #[Test]
    public function resolves_custom_meta_data(): void
    {
        $scrollProp = new ScrollProp(
            value: $this->users,
            wrapper: 'data',
            metadata: static fn () => new readonly class('usersPage', 10, 12, 11) implements ProvidesScrollMetadata {
                public function __construct(
                    public string $pageName,
                    public int|string|null $previousPage,
                    public int|string|null $nextPage,
                    public int|string|null $currentPage,
                ) {}
            },
        );

        $this->assertSame(
            [
                'pageName' => 'usersPage',
                'previousPage' => 10,
                'nextPage' => 12,
                'currentPage' => 11,
            ],
            $scrollProp->metadata(),
        );
    }

    #[Test]
    public function can_set_the_merge_intent_based_on_the_merge_intent_header(): void
    {
        $this->makeRequest();
        $appendProp = new ScrollProp($this->users);
        $appendProp->configureMergeIntent();
        $this->assertSame(['data'], $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        $this->makeRequest(headers: [Header::INFINITE_SCROLL_MERGE_INTENT => 'append']);
        $appendProp = new ScrollProp($this->users);
        $appendProp->configureMergeIntent();
        $this->assertSame(['data'], $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        $this->makeRequest(headers: [Header::INFINITE_SCROLL_MERGE_INTENT => 'prepend']);
        $prependProp = new ScrollProp($this->users);
        $prependProp->configureMergeIntent();
        $this->assertSame(['data'], $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());

        $this->makeRequest(headers: [Header::INFINITE_SCROLL_MERGE_INTENT => 'prepend']);
        $prependProp = new ScrollProp(
            value: $this->users,
            wrapper: 'items',
        );
        $prependProp->configureMergeIntent();
        $this->assertSame(['items'], $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());
    }

    #[Test]
    public function scroll_prop_value_is_resolved_only_once(): void
    {
        $callCount = 0;
        $expectedValue = ['item1', 'item2', 'item3'];

        $scrollProp = new ScrollProp(value: static function () use (&$callCount, $expectedValue): array {
            $callCount++;
            return $expectedValue;
        });

        $value1 = $scrollProp();
        $value2 = $scrollProp();
        $value3 = $scrollProp();

        $this->assertSame(1, $callCount);
        $this->assertSame($expectedValue, $value1);
        $this->assertSame($value1, $value2);
        $this->assertSame($value2, $value3);
    }

    /**
     * @return Iterator<string, array{bool}>
     */
    public static function resetUsersProp(): Iterator
    {
        yield 'no reset' => [false];
        yield 'with reset' => [true];
    }

    #[Test]
    #[DataProvider('resetUsersProp')]
    public function server_response_with_scroll_props(bool $resetUsersProp): void
    {
        $headers = $resetUsersProp ? [Header::RESET => 'users'] : [];
        $this->makeRequest(headers: $headers);

        $scrollProp = new ScrollProp(
            value: ['data' => [['id' => 1]]],
            wrapper: 'data',
            metadata: new readonly class('page', null, 2, 1) implements ProvidesScrollMetadata {
                public function __construct(
                    public string $pageName,
                    public int|string|null $previousPage,
                    public int|string|null $nextPage,
                    public int|string|null $currentPage,
                ) {}
            },
        );

        $response = new Response(
            component: 'User/Index',
            props: ['users' => $scrollProp],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame(['data' => [['id' => 1]]], $page['props']['users']);
        $this->assertSame(
            [
                'users' => [
                    'pageName' => 'page',
                    'previousPage' => null,
                    'nextPage' => 2,
                    'currentPage' => 1,
                    'reset' => $resetUsersProp,
                ],
            ],
            $page['scrollProps'],
        );
    }

    #[Test]
    public function deferred_scroll_prop_is_excluded_from_initial_request(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'Users/Index',
            props: ['users' => new ScrollProp(fn () => $this->users)->defer()],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame(['default' => ['users']], $page['deferredProps']);
        $this->assertArrayNotHasKey('scrollProps', $page);
    }

    #[Test]
    public function deferred_scroll_prop_is_resolved_on_partial_request(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'users',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: ['users' => new ScrollProp(fn () => $this->users)->defer()],
        );
        $page = $response->body;

        $this->assertArrayHasKey('scrollProps', $page);
        $this->assertArrayHasKey('users', $page['props']);
        $this->assertCount(15, $page['props']['users']->data);
        $this->assertContains('users.data', $page['mergeProps']);
    }

    #[Test]
    public function deferred_scroll_prop_can_have_custom_group(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'Users/Index',
            props: ['users' => new ScrollProp(fn () => $this->users)->defer('custom-group')],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame(['custom-group' => ['users']], $page['deferredProps']);
    }
}
