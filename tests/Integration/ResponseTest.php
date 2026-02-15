<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use GuzzleHttp\Promise\PromiseInterface;
use Inertia\Configs\InertiaConfig;
use Inertia\Contracts\Arrayable;
use Inertia\Contracts\ProvidesInertiaProperties;
use Inertia\Contracts\ProvidesScrollMetadata;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferProp;
use Inertia\Props\LazyProp;
use Inertia\Props\MergeProp;
use Inertia\Props\ScrollProp;
use Inertia\Response;
use Inertia\Support\Header;
use Inertia\Support\RenderContext;
use Inertia\Tests\Fixtures\FakeResource;
use Inertia\Tests\Fixtures\TestController;
use Inertia\Tests\TestCase;
use Iterator;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\ContentType;
use Tempest\Support\Paginator\PaginatedData;
use Tempest\Support\Paginator\Paginator;
use Tempest\View\ViewRenderer;

use function Tempest\Router\uri;

final class ResponseTest extends TestCase
{
    #[Test]
    public function server_response(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
        ]);

        $resolvedBody = $response->body->jsonSerialize();

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"}},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_deferred_prop(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new DeferProp(static fn () => 'bar'),
        ]);

        $resolvedBody = $response->body->jsonSerialize();

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"}},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"deferredProps":{"default":["foo"]}}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_deferred_prop_and_multiple_groups(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new DeferProp(static fn () => 'foo value'),
            'bar' => new DeferProp(static fn () => 'bar value'),
            'baz' => new DeferProp(static fn () => 'baz value', 'custom'),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

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

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"}},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"deferredProps":{"default":["foo","bar"],"custom":["baz"]}}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
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
        $headers = [];
        if ($resetUsersProp) {
            $headers[Header::RESET] = 'users';
        }

        $this->makeRequest(headers: $headers);

        $scrollProp = new ScrollProp(
            value: ['data' => [['id' => 1]]],
            wrapper: 'data',
            metadata: new readonly class('page', null, 2, 1) implements ProvidesScrollMetadata {
                public function __construct(
                    public string $pageName,
                    public int|null|string $previousPage,
                    public int|null|string $nextPage,
                    public int|null|string $currentPage,
                ) {}
            },
        );

        $response = $this->factory->render('User/Index', ['users' => $scrollProp]);

        $page = $response->body->inertia['page'];

        $this->assertSame('User/Index', $page['component']);
        $this->assertSame(['data' => [['id' => 1]]], $page['props']['users']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertArrayHasKey('version', $page);
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
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value'),
            'bar' => new MergeProp('bar value'),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['foo', 'bar'], $page['mergeProps']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":"foo value","bar":"bar value"},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"mergeProps":["foo","bar"]}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_merge_props_that_should_prepend(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value')->prepend(),
            'bar' => new MergeProp('bar value'),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['bar'], $page['mergeProps']);
        $this->assertSame(['foo'], $page['prependProps']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":"foo value","bar":"bar value"},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"mergeProps":["bar"],"prependProps":["foo"]}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_merge_props_that_has_nested_paths_to_append_and_prepend(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new MergeProp(['data' => [['id' => 1], ['id' => 2]]])->append('data'),
            'bar' => new MergeProp(['data' => ['items' => [['uuid' => 1], ['uuid' => 2]]]])->prepend('data.items'),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo.data'], $page['mergeProps']);
        $this->assertSame(['bar.data.items'], $page['prependProps']);
        $this->assertArrayNotHasKey('matchPropsOn', $page);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":{"data":[{"id":1},{"id":2}]},"bar":{"data":{"items":[{"uuid":1},{"uuid":2}]}}},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"mergeProps":["foo.data"],"prependProps":["bar.data.items"]}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_merge_props_that_has_nested_paths_to_append_and_prepend_with_match_on_strategies(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new MergeProp(['data' => [['id' => 1], ['id' => 2]]])->append('data', 'id'),
            'bar' => new MergeProp(['data' => ['items' => [['uuid' => 1], ['uuid' => 2]]]])->prepend(
                'data.items',
                'uuid',
            ),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo.data'], $page['mergeProps']);
        $this->assertSame(['bar.data.items'], $page['prependProps']);
        $this->assertSame(['foo.data.id', 'bar.data.items.uuid'], $page['matchPropsOn']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":{"data":[{"id":1},{"id":2}]},"bar":{"data":{"items":[{"uuid":1},{"uuid":2}]}}},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"mergeProps":["foo.data"],"prependProps":["bar.data.items"],"matchPropsOn":["foo.data.id","bar.data.items.uuid"]}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_deep_merge_props(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value')->deepMerge(),
            'bar' => new MergeProp('bar value')->deepMerge(),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['foo', 'bar'], $page['deepMergeProps']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":"foo value","bar":"bar value"},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"deepMergeProps":["foo","bar"]}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_match_on_props(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value')
                ->matchOn('foo-key')
                ->deepMerge(),
            'bar' => new MergeProp('bar value')
                ->matchOn('bar-key')
                ->deepMerge(),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['foo', 'bar'], $page['deepMergeProps']);
        $this->assertSame(['foo.foo-key', 'bar.bar-key'], $page['matchPropsOn']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":"foo value","bar":"bar value"},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"deepMergeProps":["foo","bar"],"matchPropsOn":["foo.foo-key","bar.bar-key"]}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_defer_and_merge_props(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new DeferProp(static fn () => 'foo value')->merge(),
            'bar' => new MergeProp('bar value'),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
        $this->assertSame(['foo', 'bar'], $page['mergeProps']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"bar":"bar value"},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"mergeProps":["foo","bar"],"deferredProps":{"default":["foo"]}}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function server_response_with_defer_and_deep_merge_props(): void
    {
        $this->makeRequest();
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new DeferProp(static fn () => 'foo value')->deepMerge(),
            'bar' => new MergeProp('bar value')->deepMerge(),
        ]);

        $renderer = $this->container->get(ViewRenderer::class);
        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);

        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame('bar value', $page['props']['bar']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
        $this->assertSame(['foo', 'bar'], $page['deepMergeProps']);

        $expectedJson = '{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"bar":"bar value"},"url":"\/user\/123","version":"123","clearHistory":false,"encryptHistory":false,"deepMergeProps":["foo","bar"],"deferredProps":{"default":["foo"]}}';
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($expectedJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, $renderer->render($resolvedBody));
    }

    #[Test]
    public function exclude_merge_props_from_partial_only_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'user',
        ]);
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value'),
            'bar' => new MergeProp('bar value'),
        ]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);

        $this->assertArrayHasKey('user', $page['props']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);

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
        $this->factory->version('123');

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value'),
            'bar' => new MergeProp('bar value'),
        ]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);

        $this->assertArrayHasKey('user', $page['props']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayHasKey('bar', $page['props']);
        $this->assertSame('bar value', $page['props']['bar']);
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

        $response = $this->factory->render('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            'foo' => new MergeProp('foo value'),
            'bar' => new MergeProp('bar value'),
        ]);

        $page = $response->body->jsonSerialize();

        $this->assertArrayHasKey('props', $page);
        $props = $page['props'];

        $this->assertArrayHasKey('foo', $props);
        $this->assertSame('foo value', $props['foo']);
        $this->assertArrayNotHasKey('bar', $props);
        $this->assertArrayNotHasKey('mergeProps', $page);
    }

    #[Test]
    public function xhr_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);
        $this->factory->version('123');

        $user = (object) ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
        ]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']->name);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    #[Test]
    public function resource_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);
        $this->factory->version('123');

        $resource = new FakeResource(['name' => 'Jonathan']);
        $response = $this->factory->render('User/Edit', ['user' => $resource]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    #[Test]
    public function lazy_callable_resource_response(): void
    {
        $this->makeRequest(
            uri: '/users',
            headers: [Header::INERTIA => 'true'],
        );
        $this->factory->version('123');

        $response = $this->factory->render('User/Index', [
            'users' => static fn () => [['name' => 'Jonathan']],
            'organizations' => static fn () => [['name' => 'Inertia']],
        ]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Index', $page['component']);
        $this->assertSame('/users', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([['name' => 'Jonathan']], $page['props']['users']);
        $this->assertSame([['name' => 'Inertia']], $page['props']['organizations']);
    }

    #[Test]
    public function lazy_callable_resource_partial_response(): void
    {
        $this->makeRequest(
            uri: '/users',
            headers: [
                Header::INERTIA => 'true',
                Header::PARTIAL_COMPONENT => 'User/Index',
                Header::PARTIAL_ONLY => 'users',
            ],
        );
        $this->factory->version('123');

        $response = $this->factory->render('User/Index', [
            'users' => static fn () => [['name' => 'Jonathan']],
            'organizations' => static fn () => [['name' => 'Inertia']],
        ]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Index', $page['component']);
        $this->assertSame('/users', $page['url']);
        $this->assertSame('123', $page['version']);

        $this->assertArrayHasKey('users', $page['props']);
        $this->assertSame([['name' => 'Jonathan']], $page['props']['users']);
        $this->assertArrayNotHasKey('organizations', $page['props']);
    }

    #[Test]
    public function lazy_prop_returning_pagination_is_transformed(): void
    {
        $this->container->singleton(InertiaConfig::class, static fn () => new InertiaConfig(laravel_pagination: true));

        $this->makeRequest(
            uri: '/users?page=1',
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $callable = static function (): PaginatedData {
            $users = [
                ['name' => 'Jonathan'],
                ['name' => 'Taylor'],
                ['name' => 'Jeffrey'],
            ];

            $paginator = new Paginator(
                totalItems: count($users),
                itemsPerPage: 2,
                currentPage: 1,
            );

            return $paginator->paginate(array_slice($users, 0, 2));
        };

        $this->factory->version('123');
        $response = $this->factory->render('User/Index', ['users' => $callable]);

        $page = $response->body;

        $this->assertSame('User/Index', $page['component']);
        $this->assertSame('/users?page=1', $page['url']);
        $this->assertSame('123', $page['version']);

        $paginatedUsers = $page['props']['users'];

        $this->assertArrayHasKey('data', $paginatedUsers);
        $this->assertArrayHasKey('links', $paginatedUsers);
        $this->assertArrayHasKey('current_page', $paginatedUsers);
        $this->assertArrayHasKey('total', $paginatedUsers);

        $this->assertSame([['name' => 'Jonathan'], ['name' => 'Taylor']], $paginatedUsers['data']);

        $this->assertSame('/users?page=2', $paginatedUsers['next_page_url']);
        $this->assertSame(1, $paginatedUsers['current_page']);
        $this->assertSame(3, $paginatedUsers['total']);
    }

    #[Test]
    public function lazy_prop_returning_nested_pagination_is_transformed(): void
    {
        $this->container->singleton(InertiaConfig::class, static fn () => new InertiaConfig(laravel_pagination: true));

        $this->makeRequest(
            uri: '/users?page=1',
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $callable = static function (): array {
            $users = [
                ['name' => 'Jonathan'],
                ['name' => 'Taylor'],
                ['name' => 'Jeffrey'],
            ];

            $paginator = new Paginator(
                totalItems: count($users),
                itemsPerPage: 2,
                currentPage: 1,
            );

            return [
                'users' => $paginator->paginate(array_slice($users, 0, 2)),
            ];
        };

        $this->factory->version('123');
        $response = $this->factory->render('User/Index', ['something' => $callable]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Index', $page['component']);
        $this->assertSame('/users?page=1', $page['url']);
        $this->assertSame('123', $page['version']);

        $nestedUsers = $page['props']['something']['users'];

        $this->assertArrayHasKey('data', $nestedUsers);
        $this->assertArrayHasKey('links', $nestedUsers);
        $this->assertArrayHasKey('current_page', $nestedUsers);
        $this->assertArrayHasKey('path', $nestedUsers);

        $this->assertSame([['name' => 'Jonathan'], ['name' => 'Taylor']], $nestedUsers['data']);
        $this->assertSame('/users?page=2', $nestedUsers['next_page_url']);
        $this->assertSame('/users', $nestedUsers['path']);
        $this->assertSame(3, $nestedUsers['total']);
    }

    #[Test]
    public function arrayable_prop_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
        ]);

        $this->factory->version('123');
        $resource = new FakeResource(['name' => 'Jonathan']);
        $response = $this->factory->render('User/Edit', ['user' => $resource]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    #[Test]
    public function promise_props_are_resolved(): void
    {
        $this->makeRequest(headers: [Header::INERTIA => 'true']);

        $user = (object) ['name' => 'Jonathan'];

        $promise = Mockery::mock(PromiseInterface::class)
            ->shouldReceive('wait')
            ->once()
            ->andReturn($user)
            ->getMock();

        $this->factory->version('123');
        $response = $this->factory->render('User/Edit', ['user' => $promise]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']->name);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    #[Test]
    public function xhr_partial_response(): void
    {
        $this->makeRequest(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'User/Edit',
            Header::PARTIAL_ONLY => 'partial',
        ]);

        $this->factory->version('123');
        $response = $this->factory->render('User/Edit', [
            'user' => (object) ['name' => 'Jonathan'],
            'partial' => 'partial-data',
        ]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);

        $this->assertCount(1, $page['props']);
        $this->assertArrayHasKey('partial', $page['props']);
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

        $this->factory->version('123');
        $response = $this->factory->render('User/Edit', [
            'user' => (object) ['name' => 'Jonathan'],
            'partial' => 'partial-data',
        ]);

        $page = $response->body->jsonSerialize();

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);

        $this->assertCount(1, $page['props']);
        $this->assertArrayHasKey('partial', $page['props']);
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

        $props = [
            'auth' => [
                'user' => new LazyProp(static fn () => [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ]),
                'shared_value' => 'value',
                'value' => 'value',
            ],
            'shared' => [
                'flash' => 'value',
            ],
        ];

        $response = $this->factory->render('User/Edit', $props);
        $page = $response->body;

        $this->assertArrayNotHasKey('shared', $page['props']);
        $this->assertArrayHasKey('auth', $page['props']);
        $this->assertArrayNotHasKey('value', $page['props']['auth']);
        $this->assertSame('Jonathan Reinink', $page['props']['auth']['user']['name']);
        $this->assertSame('jonathan@example.com', $page['props']['auth']['user']['email']);
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

        $props = [
            'auth' => [
                'user' => new LazyProp(static fn () => [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ]),
                'shared_value' => 'value',
            ],
            'shared' => [
                'flash' => 'value',
            ],
        ];

        $response = $this->factory->render('User/Edit', $props);
        $page = $response->body;

        $this->assertArrayNotHasKey('shared', $page['props']);
        $this->assertArrayHasKey('auth', $page['props']);
        $this->assertArrayNotHasKey('user', $page['props']['auth']);
        $this->assertSame('value', $page['props']['auth']['shared_value']);
    }

    #[Test]
    public function lazy_props_are_not_included_by_default(): void
    {
        $this->makeRequest(
            uri: '/users',
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $lazyProp = new LazyProp(static fn () => 'A lazy value');

        $response = $this->factory->render('Users', [
            'users' => [],
            'lazy' => $lazyProp,
        ]);

        $page = $response->body;

        $this->assertSame([], $page['props']['users']);
        $this->assertArrayNotHasKey('lazy', $page['props']);
    }

    #[Test]
    public function lazy_props_are_included_in_partial_reload(): void
    {
        $this->makeRequest(
            uri: '/users',
            headers: [
                Header::INERTIA => 'true',
                Header::PARTIAL_COMPONENT => 'Users',
                Header::PARTIAL_ONLY => 'lazy',
            ],
        );

        $lazyProp = new LazyProp(static fn () => 'A lazy value');

        $response = $this->factory->render('Users', [
            'users' => [],
            'lazy' => $lazyProp,
        ]);

        $page = $response->body;

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame('A lazy value', $page['props']['lazy']);
    }

    #[Test]
    public function defer_arrayable_props_are_resolved_in_partial_reload(): void
    {
        $this->makeRequest(
            uri: '/users',
            headers: [
                Header::INERTIA => 'true',
                Header::PARTIAL_COMPONENT => 'Users',
                Header::PARTIAL_ONLY => 'defer',
            ],
        );

        $deferProp = new DeferProp(static fn (): Arrayable => new class implements Arrayable {
            #[\Override]
            public function toArray(): array
            {
                return ['foo' => 'bar'];
            }
        });

        $response = $this->factory->render('Users', [
            'users' => [],
            'defer' => $deferProp,
        ]);

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

        $props = [
            'user' => new LazyProp(static fn () => [
                'name' => 'Jonathan Reinink',
                'email' => 'jonathan@example.com',
            ]),
            'data' => [
                'name' => 'Taylor Otwell',
            ],
            'errors' => new AlwaysProp(static fn () => [
                'name' => 'The email field is required.',
            ]),
        ];

        $response = $this->factory->render('User/Edit', $props);
        $page = $response->body;

        $this->assertSame('The email field is required.', $page['props']['errors']['name']);
        $this->assertSame('Taylor Otwell', $page['props']['data']['name']);
        $this->assertArrayNotHasKey('user', $page['props']);
    }

    #[Test]
    public function string_function_names_are_not_invoked_as_callables(): void
    {
        $this->makeRequest();

        $response = $this->factory->render('User/Edit', [
            'always' => new AlwaysProp('date'),
            'merge' => new MergeProp('trim'),
        ]);

        $page = $response->body->inertia['page'];

        $this->assertSame('date', $page['props']['always']);
        $this->assertSame('trim', $page['props']['merge']);
    }

    #[Test]
    public function inertia_responsable_objects(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'responsableProps']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('bar', $props['foo']);
        $this->assertSame('qux', $props['baz']);
        $this->assertSame('corge', $props['quux']);
    }

    #[Test]
    public function props_can_be_merged_with_shared_data(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'mergeWithShared']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $props = $page['props'];

        $this->assertSame(['foo', 'bar'], $props['items']);
        $this->assertSame(['foo', 'baz'], $props['deep']['foo']['bar']);
    }

    #[Test]
    public function top_level_dot_props_get_unpacked(): void
    {
        $this->makeRequest(
            uri: '/products/123',
            headers: [Header::INERTIA => 'true'],
        );

        $props = [
            'auth' => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                ],
            ],
            'auth.user.can' => [
                'do.stuff' => true,
            ],
            'product' => ['name' => 'My example product'],
        ];

        $response = $this->factory->render('User/Edit', $props);
        $page = $response->body;

        $user = $page['props']['auth']['user'];
        $this->assertSame('Jonathan Reinink', $user['name']);
        $this->assertTrue($user['can']['do.stuff']);
        $this->assertArrayNotHasKey('auth.user.can', $page['props']);
    }

    #[Test]
    public function nested_dot_props_do_not_get_unpacked(): void
    {
        $this->makeRequest(
            uri: '/products/123',
            headers: [Header::INERTIA => 'true'],
        );

        $props = [
            'auth' => [
                'user.can' => [
                    'do.stuff' => true,
                ],
                'user' => [
                    'name' => 'Jonathan Reinink',
                ],
            ],
            'product' => ['name' => 'My example product'],
        ];

        $response = $this->factory->render('User/Edit', $props);
        $page = $response->body;

        $auth = $page['props']['auth'];
        $this->assertSame('Jonathan Reinink', $auth['user']['name']);
        $this->assertTrue($auth['user.can']['do.stuff']);
        $this->assertArrayNotHasKey('can', $auth);
    }

    #[Test]
    public function props_can_be_added_using_the_with_method(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'withMethod']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('bar', $props['foo']);
        $this->assertSame('qux', $props['baz']);
        $this->assertSame('corge', $props['quux']);
        $this->assertSame('garply', $props['grault']);
    }

    #[Test]
    public function responsable_with_invalid_key(): void
    {
        $this->makeRequest(headers: [Header::INERTIA => 'true']);

        $resource = new FakeResource(["\x00*\x00_invalid_key" => 'for object']);

        $response = $this->factory->render('User/Edit', ['resource' => $resource]);
        $page = $response->body;

        $this->assertSame(["\x00*\x00_invalid_key" => 'for object'], $page['props']['resource']);
    }

    #[Test]
    public function the_page_url_is_prefixed_with_the_proxy_prefix(): void
    {
        $this->makeRequest(headers: [Header::FORWARDED_PREFIX => '/sub/directory']);

        $user = ['name' => 'Jonathan'];
        $response = $this->factory->render('User/Edit', [
            'user' => $user,
        ]);

        $page = $response->body->inertia['page'];
        $resolvedBody = $response->body->jsonSerialize();

        $this->assertSame('/sub/directory/user/123', $page['url']);
    }

    #[Test]
    public function the_page_url_doesnt_double_up(): void
    {
        $this->makeRequest(
            uri: '/subpath/product/122',
            headers: [Header::INERTIA => 'true'],
        );

        $response = $this->factory->render('Product/Show', []);
        $page = $response->body;

        $this->assertSame('/subpath/product/122', $page['url']);
    }

    #[Test]
    public function trailing_slashes_in_a_url_are_preserved(): void
    {
        $this->makeRequest(
            uri: '/users/',
            headers: [Header::INERTIA => 'true'],
        );

        $response = $this->factory->render('User/Index', []);
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

        $response = $this->factory->render('User/Index', []);
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

        $response = $this->factory->render('User/Index', []);
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

        $response = $this->factory->render('User/Index', []);
        $page = $response->body;

        $this->assertSame('/users?page=1&sort=name', $page['url']);
    }

    #[Test]
    public function deferred_props_from_provides_inertia_properties_are_included_in_deferred_props_metadata(): void
    {
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

        $page = $response->body->inertia['page'];

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
    }

    #[Test]
    public function merge_props_from_provides_inertia_properties_are_included_in_merge_props_metadata(): void
    {
        $response = new Response('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaProperties {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new MergeProp('foo value'),
                    ];
                }
            },
        ]);

        $page = $response->body->inertia['page'];

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame(['foo'], $page['mergeProps']);
    }

    #[Test]
    public function deferred_merge_props_from_provides_inertia_properties_include_both_metadata(): void
    {
        $response = new Response(
            component: 'User/Edit',
            props: [
                'user' => ['name' => 'Jonathan'],
                new class implements ProvidesInertiaProperties {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return [
                            'foo' => new DeferProp(static fn () => 'foo value', 'default')->merge(),
                        ];
                    }
                },
            ],
        );

        $page = $response->body->inertia['page'];

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame(['default' => ['foo']], $page['deferredProps']);
        $this->assertSame(['foo'], $page['mergeProps']);
    }
}
