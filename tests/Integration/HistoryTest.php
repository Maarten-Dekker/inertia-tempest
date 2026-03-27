<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Configs\HistoryConfig;
use Inertia\Configs\InertiaConfig;
use Inertia\Support\Header;
use Inertia\Tests\Fixtures\TestController;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function Tempest\Router\uri;

final class HistoryTest extends TestCase
{
    #[Test]
    public function optional_navigation_flags_are_absent_by_default(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRenderWithMiddleware']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('preserveFragment', $page);
    }

    #[Test]
    public function the_history_can_be_encrypted(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'encryptHistory']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertTrue($page['encryptHistory']);
    }

    #[Test]
    public function the_history_can_be_encrypted_via_middleware(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'encryptHistoryWithMiddleware']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertTrue($page['encryptHistory']);
    }

    #[Test]
    public function the_history_can_be_encrypted_globally(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(history: new HistoryConfig(encrypt: true)),
        );

        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRenderWithMiddleware']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertTrue($page['encryptHistory']);
    }

    #[Test]
    public function the_history_can_be_encrypted_globally_and_overridden(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(history: new HistoryConfig(encrypt: true)),
        );

        $response = $this->http->get(
            uri: uri([TestController::class, 'encryptHistoryOverride']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayNotHasKey('encryptHistory', $page);
    }

    #[Test]
    public function the_history_can_be_cleared(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'clearHistory']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertTrue($page['clearHistory']);
    }

    #[Test]
    public function the_history_can_be_cleared_when_redirecting(): void
    {
        $this->http->get(
            uri: uri([TestController::class, 'clearHistoryAndRedirect']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRender']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertTrue($page['clearHistory']);
    }

    #[Test]
    public function the_url_fragment_can_be_preserved(): void
    {
        $this->http->get(uri: uri([TestController::class, 'preserveUrlFragmentAfterRedirect']));

        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRender']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('preserveFragment', $page);
    }
}
