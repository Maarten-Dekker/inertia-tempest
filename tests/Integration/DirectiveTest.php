<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Configs\InertiaConfig;
use Inertia\Configs\PageConfig;
use Inertia\Configs\SsrConfig;
use Inertia\Ssr\Contracts\Gateway;
use Inertia\Ssr\Response;
use Inertia\Tests\Fixtures\FakeGateway;
use Inertia\Tests\TestCase;
use Inertia\Views\InertiaView;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class DirectiveTest extends TestCase
{
    #[Test]
    public function inertia_method_renders_the_root_element(): void
    {
        $view = new InertiaView(
            path: 'inertia.view.php',
            inertia: ['page' => self::EXAMPLE_PAGE_OBJECT],
            ssrHead: null,
            ssrBody: null,
        );

        $expectedJson = json_encode(self::EXAMPLE_PAGE_OBJECT);
        $expectedHtml =
            '<script data-page="app" type="application/json">' . $expectedJson . '</script><div id="app"></div>';

        $this->assertSame($expectedHtml, (string) $view->inertia());
    }

    #[Test]
    public function inertia_directive_renders_the_root_element_with_data_attribute(): void
    {
        $this->container->singleton(InertiaConfig::class, static fn () => new InertiaConfig(
            ssr: new SsrConfig(enabled: false),
            pages: new PageConfig(use_data_attribute_for_initial_page: true),
        ));

        $response = $this->factory->render('Foo/Bar', self::EXAMPLE_PAGE_OBJECT['props']);
        $view = $response->body;

        $pageJson = json_encode($view->inertia['page'], JSON_THROW_ON_ERROR);
        $expectedHtml = '<div id="app" data-page="' . htmlspecialchars($pageJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, (string) $view->inertia());
    }

    #[Test]
    public function inertia_directive_renders_server_side_rendered_content_when_enabled(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(ssr: new SsrConfig(enabled: true)),
        );

        $ssrResponse = new Response(
            head: '<title>SSR Head</title>',
            body: '<p>This is some example SSR content</p>',
        );
        $mockGateway = Mockery::mock(Gateway::class)
            ->shouldReceive('dispatch')
            ->once()
            ->andReturn($ssrResponse)
            ->getMock();
        $this->container->singleton(Gateway::class, static fn () => $mockGateway);

        $response = $this->factory->render('User/Edit', self::EXAMPLE_PAGE_OBJECT['props']);
        $renderedHtml = (string) $response->body->inertia();

        $this->assertSame('<p>This is some example SSR content</p>', $renderedHtml);
    }

    #[Test]
    public function inertia_directive_can_use_a_different_root_element_id(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(ssr: new SsrConfig(enabled: false)),
        );

        $response = $this->factory->render('Foo/Bar', self::EXAMPLE_PAGE_OBJECT['props']);
        $view = $response->body;

        $expectedJson = '{"component":"Foo\/Bar","props":{"foo":"bar"},"url":"\/","version":""}';
        $expectedHtml =
            '<script data-page="foo" type="application/json">' . $expectedJson . '</script><div id="foo"></div>';

        $this->assertSame($expectedHtml, (string) $view->inertia('foo'));
    }

    #[Test]
    public function inertia_directive_can_use_a_different_root_element_id_when_using_data_attribute(): void
    {
        $this->container->singleton(InertiaConfig::class, static fn () => new InertiaConfig(
            ssr: new SsrConfig(enabled: false),
            pages: new PageConfig(use_data_attribute_for_initial_page: true),
        ));

        $response = $this->factory->render('Foo/Bar', self::EXAMPLE_PAGE_OBJECT['props']);
        $view = $response->body;

        $pageJson = json_encode($view->inertia['page'], JSON_THROW_ON_ERROR);
        $expectedHtml = '<div id="foo" data-page="' . htmlspecialchars($pageJson, ENT_QUOTES) . '"></div>';

        $this->assertSame($expectedHtml, (string) $view->inertia('foo'));
    }

    #[Test]
    public function inertia_head_renders_nothing_when_ssr_is_disabled(): void
    {
        $view = new InertiaView(
            path: 'inertia.view.php',
            inertia: ['page' => self::EXAMPLE_PAGE_OBJECT],
            ssrHead: null,
            ssrBody: null,
        );

        $this->assertEmpty((string) $view->inertiaHead());
    }

    #[Test]
    public function inertia_head_renders_ssr_head_when_enabled(): void
    {
        $view = new InertiaView(
            path: 'inertia.view.php',
            inertia: ['page' => self::EXAMPLE_PAGE_OBJECT],
            ssrHead: '<title inertia>Example SSR Title</title>',
            ssrBody: '',
        );

        $this->assertSame('<title inertia>Example SSR Title</title>', (string) $view->inertiaHead());
    }

    #[Test]
    public function the_server_side_rendering_request_is_dispatched_only_once_per_request(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(ssr: new SsrConfig(enabled: true)),
        );

        $gateway = new FakeGateway();
        $this->container->singleton(Gateway::class, static fn () => $gateway);

        $response = $this->factory->render('User/Edit', self::EXAMPLE_PAGE_OBJECT['props']);

        $head = (string) $response->body->inertiaHead();
        $body = (string) $response->body->inertia();

        $this->assertSame(1, $gateway->times);
        $this->assertSame('<title inertia>Example SSR Title</title>', $head);
        $this->assertSame('<p>This is some example SSR content</p>', $body);
    }
}
