<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use GuzzleHttp\Promise\PromiseInterface;
use Inertia\Configs\InertiaConfig;
use Inertia\Contracts\Arrayable;
use Inertia\Contracts\ProvidesInertiaProperties;
use Inertia\Contracts\ProvidesScrollMetadata;
use Inertia\Inertia;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OptionalProp;
use Inertia\Props\ScrollProp;
use Inertia\Response;
use Inertia\Support\Header;
use Inertia\Support\RenderContext;
use Inertia\Tests\Fixtures\TestController;
use Inertia\Tests\TestCase;
use Iterator;
use Mockery;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Duration;
use Tempest\Support\Paginator\Paginator;

use function Tempest\Router\uri;

final class ResponseTest extends TestCase
{
    #[Test]
    public function server_response(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
            ],
            version: '123',
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    #[Test]
    public function server_response_with_deferred_prop(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'foo' => new DeferProp(static fn () => 'bar'),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
    }

    #[Test]
    public function server_response_with_deferred_prop_and_multiple_groups(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'foo' => new DeferProp(static fn () => 'foo value'),
                'bar' => new DeferProp(static fn () => 'bar value'),
                'baz' => new DeferProp(static fn () => 'baz value', 'custom'),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertArrayNotHasKey('bar', $page['props']);
        $this->assertArrayNotHasKey('baz', $page['props']);
        $this->assertSame(
            [
                'default' => ['foo', 'bar'],
                'custom' => ['baz'],
            ],
            $page['deferredProps'],
        );
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
    public function server_response_with_merge_props(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'foo' => new MergeProp('foo value'),
                'bar' => new MergeProp('bar value'),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['foo', 'bar'], $page['mergeProps']);
    }

    #[Test]
    public function server_response_with_merge_props_that_should_prepend(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'foo' => new MergeProp('foo value')->prepend(),
                'bar' => new MergeProp('bar value'),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame(['bar'], $page['mergeProps']);
        $this->assertSame(['foo'], $page['prependProps']);
    }

    #[Test]
    public function server_response_with_merge_props_that_has_nested_paths_to_append_and_prepend(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => new MergeProp(['data' => [['id' => 1], ['id' => 2]]])->append('data'),
                'bar' => new MergeProp(['data' => ['items' => [['uuid' => 1], ['uuid' => 2]]]])->prepend('data.items'),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame(['foo.data'], $page['mergeProps']);
        $this->assertSame(['bar.data.items'], $page['prependProps']);
        $this->assertArrayNotHasKey('matchPropsOn', $page);
    }

    #[Test]
    public function server_response_with_merge_props_that_has_nested_paths_to_append_and_prepend_with_match_on_strategies(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => new MergeProp(['data' => [['id' => 1], ['id' => 2]]])->append('data', 'id'),
                'bar' => new MergeProp(['data' => ['items' => [['uuid' => 1], ['uuid' => 2]]]])->prepend(
                    'data.items',
                    'uuid',
                ),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame(['foo.data'], $page['mergeProps']);
        $this->assertSame(['bar.data.items'], $page['prependProps']);
        $this->assertSame(['foo.data.id', 'bar.data.items.uuid'], $page['matchPropsOn']);
    }

    #[Test]
    public function server_response_with_deep_merge_props(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => new MergeProp('foo value')->deepMerge(),
                'bar' => new MergeProp('bar value')->deepMerge(),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['foo', 'bar'], $page['deepMergeProps']);
    }

    #[Test]
    public function server_response_with_match_on_props(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => new MergeProp('foo value')
                    ->matchOn('foo-key')
                    ->deepMerge(),
                'bar' => new MergeProp('bar value')
                    ->matchOn('bar-key')
                    ->deepMerge(),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame(['foo', 'bar'], $page['deepMergeProps']);
        $this->assertSame(['foo.foo-key', 'bar.bar-key'], $page['matchPropsOn']);
    }

    #[Test]
    public function server_response_with_defer_and_merge_props(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => new DeferProp(static fn () => 'foo value')->merge(),
                'bar' => new MergeProp('bar value'),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
        $this->assertSame(['foo', 'bar'], $page['mergeProps']);
    }

    #[Test]
    public function server_response_with_defer_and_deep_merge_props(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => new DeferProp(static fn () => 'foo value')->deepMerge(),
                'bar' => new MergeProp('bar value')->deepMerge(),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
        $this->assertSame(['foo', 'bar'], $page['deepMergeProps']);
    }

    #[Test]
    public function inertia_response_header_is_present_without_accessing_body(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $response = inertia()->render('SomeComponent');

        $this->assertSame('true', $response->getHeader(Header::INERTIA)?->first());
        $this->assertSame('application/json', $response->getHeader('Content-Type')?->first());
    }

    #[Test]
    public function exclude_merge_props_from_partial_only_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'user',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'foo' => new MergeProp('foo value'),
                'bar' => new MergeProp('bar value'),
            ],
        );
        $page = $response->body;

        $this->assertArrayHasKey('user', $page['props']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertArrayNotHasKey('bar', $page['props']);
        $this->assertArrayNotHasKey('mergeProps', $page);
    }

    #[Test]
    public function exclude_merge_props_from_partial_except_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_EXCEPT => 'foo',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'foo' => new MergeProp('foo value'),
                'bar' => new MergeProp('bar value'),
            ],
        );
        $page = $response->body;

        $this->assertArrayHasKey('bar', $page['props']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame(['bar'], $page['mergeProps']);
    }

    #[Test]
    public function exclude_merge_props_when_passed_in_reset_header(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'foo',
            Header::RESET => 'foo',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => new MergeProp('foo value'),
                'bar' => new MergeProp('bar value'),
            ],
        );
        $page = $response->body;

        $this->assertArrayHasKey('foo', $page['props']);
        $this->assertArrayNotHasKey('bar', $page['props']);
        $this->assertArrayNotHasKey('mergeProps', $page);
    }

    #[Test]
    public function xhr_response_with_deferred_props_includes_deferred_metadata(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'results' => new DeferProp(static fn () => ['data' => ['item1', 'item2']], 'default'),
            ],
        );
        $page = $response->body;

        $this->assertArrayNotHasKey('results', $page['props']);
        $this->assertSame(['default' => ['results']], $page['deferredProps']);
    }

    #[Test]
    public function arrayable_props_are_resolved_to_arrays(): void
    {
        $this->makeRequest(headers: [Header::INERTIA => 'true']);
        $response = new Response(
            component: 'User/Edit',
            props: ['user' => new readonly class(['name' => 'Jonathan']) implements Arrayable {
                public function __construct(
                    private array $data,
                ) {}

                public function toArray(): array
                {
                    return $this->data;
                }
            }],
        );

        $this->assertSame(['name' => 'Jonathan'], $response->body['props']['user']);
    }

    #[Test]
    public function optional_callable_resource_response(): void
    {
        $this->makeRequest(
            uri: '/users',
            headers: [Header::INERTIA => 'true'],
        );

        $response = new Response(
            component: 'User/Index',
            props: [
                'users' => static fn () => [['name' => 'Jonathan']],
                'organizations' => static fn () => [['name' => 'Inertia']],
            ],
        );
        $page = $response->body;

        $this->assertSame([['name' => 'Jonathan']], $page['props']['users']);
        $this->assertSame([['name' => 'Inertia']], $page['props']['organizations']);
    }

    #[Test]
    public function optional_callable_resource_partial_response(): void
    {
        $this->makeRequest(
            uri: '/users',
            headers: [
                Header::INERTIA => 'true',
                Header::PARTIAL_COMPONENT => 'User/Index',
                Header::PARTIAL_ONLY => 'users',
            ],
        );

        $response = new Response(
            component: 'User/Index',
            props: [
                'users' => static fn () => [['name' => 'Jonathan']],
                'organizations' => static fn () => [['name' => 'Inertia']],
            ],
        );
        $page = $response->body;

        $this->assertSame([['name' => 'Jonathan']], $page['props']['users']);
        $this->assertArrayNotHasKey('organizations', $page['props']);
    }

    #[Test]
    public function pagination_is_transformed(): void
    {
        $this->container->singleton(InertiaConfig::class, static fn () => new InertiaConfig(laravel_pagination: true));
        $this->makeRequest(
            uri: '/users?page=1',
            headers: [Header::INERTIA => 'true'],
        );

        $paginator = new Paginator(
            totalItems: 3,
            itemsPerPage: 2,
            currentPage: 1,
        );
        $users = [['name' => 'Jonathan'], ['name' => 'Taylor'], ['name' => 'Jeffrey']];

        $response = new Response(
            component: 'User/Index',
            props: ['users' => static fn () => $paginator->paginate(array_slice($users, 0, 2))],
        );
        $paginatedUsers = $response->body['props']['users'];

        $this->assertSame([['name' => 'Jonathan'], ['name' => 'Taylor']], $paginatedUsers['data']);
        $this->assertSame('/users?page=2', $paginatedUsers['next_page_url']);
        $this->assertSame(1, $paginatedUsers['current_page']);
        $this->assertSame(3, $paginatedUsers['total']);
    }

    #[Test]
    public function nested_pagination_is_transformed(): void
    {
        $this->container->singleton(InertiaConfig::class, static fn () => new InertiaConfig(laravel_pagination: true));
        $this->makeRequest(
            uri: '/users?page=1',
            headers: [Header::INERTIA => 'true'],
        );

        $paginator = new Paginator(
            totalItems: 3,
            itemsPerPage: 2,
            currentPage: 1,
        );
        $users = [['name' => 'Jonathan'], ['name' => 'Taylor'], ['name' => 'Jeffrey']];

        $response = new Response(
            component: 'User/Index',
            props: ['something' => static fn () => ['users' => $paginator->paginate(array_slice($users, 0, 2))]],
        );
        $nestedUsers = $response->body['props']['something']['users'];

        $this->assertSame([['name' => 'Jonathan'], ['name' => 'Taylor']], $nestedUsers['data']);
        $this->assertSame('/users?page=2', $nestedUsers['next_page_url']);
        $this->assertSame('/users', $nestedUsers['path']);
        $this->assertSame(3, $nestedUsers['total']);
    }

    #[Test]
    public function promise_props_are_resolved(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $promise = Mockery::mock(PromiseInterface::class)
            ->shouldReceive('wait')
            ->once()
            ->andReturn(['name' => 'Jonathan'])
            ->getMock();

        $response = new Response(
            component: 'User/Edit',
            props: ['user' => $promise],
        );

        $this->assertSame('Jonathan', $response->body['props']['user']['name']);
    }

    #[Test]
    public function xhr_partial_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'partial',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'partial' => 'partial-data',
            ],
        );
        $page = $response->body;

        $this->assertSame('partial-data', $page['props']['partial']);
        $this->assertArrayNotHasKey('user', $page['props']);
    }

    #[Test]
    public function exclude_props_from_partial_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_EXCEPT => 'user',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                'partial' => 'partial-data',
            ],
        );
        $page = $response->body;

        $this->assertSame('partial-data', $page['props']['partial']);
        $this->assertArrayNotHasKey('user', $page['props']);
    }

    #[Test]
    public function nested_partial_props(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'auth.user,auth.shared_value',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'auth' => [
                    'user' => new OptionalProp(static fn () => [
                        'name' => 'Jonathan Reinink',
                        'email' => 'jonathan@example.com',
                    ]),
                    'shared_value' => 'value',
                    'value' => 'value',
                ],
                'shared' => ['flash' => 'value'],
            ],
        );
        $page = $response->body;

        $this->assertArrayNotHasKey('shared', $page['props']);
        $this->assertArrayNotHasKey('value', $page['props']['auth']);
        $this->assertSame('Jonathan Reinink', $page['props']['auth']['user']['name']);
        $this->assertSame('value', $page['props']['auth']['shared_value']);
    }

    #[Test]
    public function exclude_nested_props_from_partial_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'auth',
            Header::PARTIAL_EXCEPT => 'auth.user',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'auth' => [
                    'user' => new OptionalProp(static fn () => ['name' => 'Jonathan Reinink']),
                    'shared_value' => 'value',
                ],
                'shared' => ['flash' => 'value'],
            ],
        );
        $page = $response->body;

        $this->assertArrayNotHasKey('shared', $page['props']);
        $this->assertArrayNotHasKey('user', $page['props']['auth']);
        $this->assertSame('value', $page['props']['auth']['shared_value']);
    }

    #[Test]
    public function optional_props_are_not_included_by_default(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $response = new Response(
            component: 'Users',
            props: [
                'users' => [],
                'optional' => new OptionalProp(static fn () => 'An optional value'),
            ],
        );
        $page = $response->body;

        $this->assertSame([], $page['props']['users']);
        $this->assertArrayNotHasKey('optional', $page['props']);
    }

    #[Test]
    public function optional_props_are_included_in_partial_reload(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'Users',
            Header::PARTIAL_ONLY => 'optional',
        ]);

        $response = new Response(
            component: 'Users',
            props: [
                'users' => [],
                'optional' => new OptionalProp(static fn () => 'An optional value'),
            ],
        );
        $page = $response->body;

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame('An optional value', $page['props']['optional']);
    }

    #[Test]
    public function defer_arrayable_props_are_resolved_in_partial_reload(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'Users',
            Header::PARTIAL_ONLY => 'defer',
        ]);

        $response = new Response(
            component: 'Users',
            props: [
                'users' => [],
                'defer' => new DeferProp(static fn (): Arrayable => new class implements Arrayable {
                    #[Override]
                    public function toArray(): array
                    {
                        return ['foo' => 'bar'];
                    }
                }),
            ],
        );
        $page = $response->body;

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame(['foo' => 'bar'], $page['props']['defer']);
    }

    #[Test]
    public function always_props_are_included_on_partial_reload(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'data',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => new OptionalProp(static fn () => ['name' => 'Jonathan Reinink']),
                'data' => ['name' => 'Taylor Otwell'],
                'errors' => new AlwaysProp(static fn () => ['name' => 'The email field is required.']),
            ],
        );
        $page = $response->body;

        $this->assertSame('The email field is required.', $page['props']['errors']['name']);
        $this->assertSame('Taylor Otwell', $page['props']['data']['name']);
        $this->assertArrayNotHasKey('user', $page['props']);
    }

    #[Test]
    public function string_function_names_are_not_invoked_as_callables(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'always' => new AlwaysProp('date'),
                'merge' => new MergeProp('trim'),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('date', $page['props']['always']);
        $this->assertSame('trim', $page['props']['merge']);
    }

    #[Test]
    public function array_callable_syntax_props_are_not_invoked(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'always' => new AlwaysProp([DateTime::class, 'now']),
                'merge' => new MergeProp([DateTime::class, 'now']),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame([DateTime::class, 'now'], $page['props']['always']);
        $this->assertSame([DateTime::class, 'now'], $page['props']['merge']);
    }

    #[Test]
    public function inertia_responsable_objects(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'responsableProps']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('qux', $page['props']['baz']);
        $this->assertSame('corge', $page['props']['quux']);
    }

    #[Test]
    public function props_can_be_merged_with_shared_data(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'mergeWithShared']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;

        $this->assertSame(['foo', 'bar'], $page['props']['items']);
        $this->assertSame(['foo', 'baz'], $page['props']['deep']['foo']['bar']);
    }

    #[Test]
    public function top_level_dot_props_get_unpacked(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'auth' => ['user' => ['name' => 'Jonathan Reinink']],
                'auth.user.can' => ['do.stuff' => true],
                'product' => ['name' => 'My example product'],
            ],
        );
        $user = $response->body['props']['auth']['user'];

        $this->assertSame('Jonathan Reinink', $user['name']);
        $this->assertTrue($user['can']['do.stuff']);
        $this->assertArrayNotHasKey('auth.user.can', $response->body['props']);
    }

    #[Test]
    public function nested_dot_props_do_not_get_unpacked(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'auth' => [
                    'user.can' => ['do.stuff' => true],
                    'user' => ['name' => 'Jonathan Reinink'],
                ],
            ],
        );
        $page = $response->body;

        $this->assertSame('Jonathan Reinink', $page['props']['auth']['user']['name']);
        $this->assertTrue($page['props']['auth']['user.can']['do.stuff']);
        $this->assertArrayNotHasKey('can', $page['props']['auth']);
    }

    #[Test]
    public function props_can_be_added_using_the_with_method(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'withMethod']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('qux', $page['props']['baz']);
        $this->assertSame('corge', $page['props']['quux']);
        $this->assertSame('garply', $page['props']['grault']);
    }

    #[Test]
    public function once_props_are_always_resolved_on_initial_page_load(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar'),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function fresh_once_props_are_included_on_initial_page_load(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar')->fresh(),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function once_props_are_resolved_with_a_custom_key_and_ttl_value(): void
    {
        $this->makeRequest();

        $clock = $this->clock();
        $clock->setNow('2025-01-01 12:00:00');

        $expiresAt = DateTime::now()->plus(Duration::minute())->getTimestamp()->getMilliseconds();

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()
                    ->once(static fn () => 'bar')
                    ->as('baz')
                    ->until(Duration::minute()),
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame(['baz' => ['prop' => 'foo', 'expiresAt' => $expiresAt]], $page['onceProps']);
    }

    #[Test]
    public function once_props_are_not_resolved_on_subsequent_requests_when_they_are_in_the_once_props_header(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::EXCEPT_ONCE_PROPS => 'foo',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar'),
            ],
        );
        $page = $response->body;

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function once_props_are_resolved_on_subsequent_requests_when_the_once_props_header_is_missing(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar'),
            ],
        );
        $page = $response->body;

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function once_props_are_resolved_on_subsequent_requests_when_they_are_not_in_the_once_props_header(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::EXCEPT_ONCE_PROPS => 'baz',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar'),
            ],
        );
        $page = $response->body;

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function once_props_are_resolved_on_partial_requests_when_included_in_only_headers(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'foo',
            Header::EXCEPT_ONCE_PROPS => 'foo',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar'),
                'baz' => inertia()->once(static fn () => 'qux'),
            ],
        );
        $page = $response->body;

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertArrayNotHasKey('baz', $page['props']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function once_props_are_not_resolved_on_partial_requests_when_excluded_in_except_headers(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_EXCEPT => 'foo',
            Header::EXCEPT_ONCE_PROPS => 'foo',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar'),
                'baz' => inertia()->once(static fn () => 'qux'),
            ],
        );
        $page = $response->body;

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame('qux', $page['props']['baz']);
        $this->assertSame(['baz' => ['prop' => 'baz', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function fresh_props_are_resolved_even_when_in_except_once_props_header(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::EXCEPT_ONCE_PROPS => 'foo',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar')->fresh(),
            ],
        );
        $page = $response->body;

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function fresh_props_are_not_excluded_while_once_props_are_excluded(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::EXCEPT_ONCE_PROPS => 'foo,baz',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'foo' => inertia()->once(static fn () => 'bar')->fresh(),
                'baz' => inertia()->once(static fn () => 'qux'),
            ],
        );
        $page = $response->body;

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertArrayNotHasKey('baz', $page['props']);
        $this->assertSame(
            [
                'foo' => ['prop' => 'foo', 'expiresAt' => null],
                'baz' => ['prop' => 'baz', 'expiresAt' => null],
            ],
            $page['onceProps'],
        );
    }

    #[Test]
    public function defer_props_that_are_once_and_already_loaded_are_excluded(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::EXCEPT_ONCE_PROPS => 'defer',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'defer' => inertia()->defer(static fn () => 'value')->once(),
            ],
        );
        $page = $response->body;

        $this->assertArrayNotHasKey('defer', $page['props']);
        $this->assertArrayNotHasKey('deferredProps', $page);
        $this->assertSame(['defer' => ['prop' => 'defer', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function defer_props_that_are_once_and_already_loaded_not_excluded_when_explicitly_requested(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'defer',
            Header::EXCEPT_ONCE_PROPS => 'defer',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'defer' => inertia()->defer(static fn () => 'value')->once(),
            ],
        );
        $page = $response->body;

        $this->assertSame('value', $page['props']['defer']);
        $this->assertSame(['defer' => ['prop' => 'defer', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function arrayable_with_invalid_key(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: ['resource' => new readonly class(["\x00*\x00_invalid_key" => 'for object']) implements Arrayable {
                public function __construct(
                    private array $data,
                ) {}

                public function toArray(): array
                {
                    return $this->data;
                }
            }],
        );
        $page = $response->body;

        $this->assertSame(["\x00*\x00_invalid_key" => 'for object'], $page['props']['resource']);
    }

    #[Test]
    public function the_page_url_is_prefixed_with_the_proxy_prefix(): void
    {
        $this->makeRequest(headers: [
            Header::FORWARDED_PREFIX => '/sub/directory',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('/sub/directory/user/123', $page['url']);
    }

    #[Test]
    public function the_page_url_doesnt_double_up(): void
    {
        $this->makeRequest(
            uri: '/subpath/product/122',
            headers: [Header::INERTIA => 'true'],
        );

        $response = new Response(
            component: 'Product/Show',
            props: [],
        );

        $this->assertSame('/subpath/product/122', $response->body['url']);
    }

    #[Test]
    public function trailing_slashes_in_a_url_are_preserved(): void
    {
        $this->makeRequest(
            uri: '/users/',
            headers: [Header::INERTIA => 'true'],
        );

        $response = new Response(
            component: 'User/Edit',
            props: [],
        );
        $page = $response->body;

        $this->assertSame('/users/', $page['url']);
    }

    #[Test]
    public function trailing_slashes_in_a_url_with_query_parameters_are_preserved(): void
    {
        $this->makeRequest(
            uri: '/users/?page=1&sort=name',
            headers: [Header::INERTIA => 'true'],
        );

        $response = new Response(
            component: 'User/Edit',
            props: [],
        );
        $page = $response->body;

        $this->assertSame('/users/?page=1&sort=name', $page['url']);
    }

    #[Test]
    public function a_url_without_trailing_slash_is_resolved_correctly(): void
    {
        $this->makeRequest(
            uri: '/users',
            headers: [Header::INERTIA => 'true'],
        );

        $response = new Response(
            component: 'User/Edit',
            props: [],
        );
        $page = $response->body;

        $this->assertSame('/users', $page['url']);
    }

    #[Test]
    public function a_url_without_trailing_slash_and_query_parameters_is_resolved_correctly(): void
    {
        $this->makeRequest(
            uri: '/users?page=1&sort=name',
            headers: [Header::INERTIA => 'true'],
        );

        $response = new Response(
            component: 'User/Edit',
            props: [],
        );
        $page = $response->body;

        $this->assertSame('/users?page=1&sort=name', $page['url']);
    }

    #[Test]
    public function deferred_props_from_provides_inertia_properties_are_included_in_deferred_props_metadata(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                new class implements ProvidesInertiaProperties {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return [
                            'foo' => new DeferProp(static fn () => 'bar', 'default'),
                        ];
                    }
                },
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
    }

    #[Test]
    public function deferred_props_from_provides_inertia_properties_with_multiple_groups(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                new class implements ProvidesInertiaProperties {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return [
                            'foo' => new DeferProp(static fn () => 'foo value', 'default'),
                            'bar' => new DeferProp(static fn () => 'bar value', 'custom'),
                        ];
                    }
                },
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertArrayNotHasKey('bar', $page['props']);
        $this->assertSame(['default' => ['foo'], 'custom' => ['bar']], $page['deferredProps']);
    }

    #[Test]
    public function deferred_props_from_provides_inertia_properties_can_be_loaded_via_partial_request(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'foo',
        ]);

        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                new class implements ProvidesInertiaProperties {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return [
                            'foo' => new DeferProp(static fn () => 'bar', 'default'),
                        ];
                    }
                },
            ],
        );
        $page = $response->body;

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertArrayNotHasKey('user', $page['props']);
    }

    #[Test]
    public function merge_props_from_provides_inertia_properties_are_included_in_merge_props_metadata(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                new class implements ProvidesInertiaProperties {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return ['foo' => new MergeProp('foo value')];
                    }
                },
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame(['foo'], $page['mergeProps']);
    }

    #[Test]
    public function once_props_from_provides_inertia_properties_are_included_in_once_props_metadata(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                new class implements ProvidesInertiaProperties {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return ['foo' => Inertia::once(static fn () => 'bar')];
                    }
                },
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    #[Test]
    public function deferred_merge_props_from_provides_inertia_properties_include_both_metadata(): void
    {
        $this->makeRequest();
        $response = new Response(
            component: 'User/Edit',
            props: [
                new class implements ProvidesInertiaProperties {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return ['foo' => new DeferProp(static fn () => 'foo value', 'default')->merge()];
                    }
                },
            ],
        );
        $page = $response->body->inertia['page'];

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
        $this->assertSame(['foo'], $page['mergeProps']);
    }
}
