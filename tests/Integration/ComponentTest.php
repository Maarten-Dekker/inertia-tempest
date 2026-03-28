<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Tests\TestCase;
use Inertia\Views\InertiaView;
use PHPUnit\Framework\Attributes\Test;
use Tempest\View\ViewRenderer;

final class ComponentTest extends TestCase
{
    #[Test]
    public function head_renders_fallback_when_ssr_disabled(): void
    {
        $rendered = $this->renderHead();

        $this->assertStringContainsString('<title>Fallback Title</title>', $rendered);
    }

    #[Test]
    public function head_renders_ssr_content_when_ssr_enabled(): void
    {
        $rendered = $this->renderHead(ssrHead: '<title inertia>SSR Title</title>');

        $this->assertStringContainsString('<title inertia>SSR Title</title>', $rendered);
    }

    #[Test]
    public function head_suppresses_fallback_when_ssr_enabled(): void
    {
        $rendered = $this->renderHead(ssrHead: '<title inertia>SSR Title</title>');

        $this->assertStringNotContainsString('<title>Fallback Title</title>', $rendered);
    }

    #[Test]
    public function app_renders_client_div_when_ssr_disabled(): void
    {
        $rendered = $this->renderApp();

        $this->assertStringContainsString('<div id="app">', $rendered);
        $this->assertStringContainsString('application/json', $rendered);
    }

    #[Test]
    public function app_renders_ssr_content_when_ssr_enabled(): void
    {
        $rendered = $this->renderApp(ssrBody: '<p>SSR Content</p>');

        $this->assertStringContainsString('<p>SSR Content</p>', $rendered);
        $this->assertStringNotContainsString('<div id="app">', $rendered);
    }

    private function renderHead(?string $ssrHead = null): string
    {
        return $this->container->get(ViewRenderer::class)->render(new InertiaView(
            path: '<x-inertia-head><title>Fallback Title</title></x-inertia-head>',
            inertia: [],
            ssrHead: $ssrHead,
        ));
    }

    private function renderApp(?string $ssrBody = null): string
    {
        return $this->container->get(ViewRenderer::class)->render(new InertiaView(
            path: '<x-inertia-app />',
            inertia: ['page' => self::EXAMPLE_PAGE_OBJECT],
            ssrBody: $ssrBody,
        ));
    }
}
