<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Configs\HistoryConfig;
use Inertia\Configs\InertiaConfig;
use Inertia\Support\Header;
use Inertia\Tests\Fixtures\TestController;
use Inertia\Tests\TestCase;

use function Tempest\Router\uri;

class HistoryTest extends TestCase
{
    public function test_the_history_is_not_encrypted_or_cleared_by_default(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRenderWithMiddleware']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertFalse($page['clearHistory']);
    }

    public function test_the_history_can_be_encrypted(): void
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

    public function test_the_history_can_be_encrypted_via_middleware(): void
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

    public function test_the_history_can_be_encrypted_globally(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            fn() => new InertiaConfig(history: new HistoryConfig(encrypt: true)),
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

    public function test_the_history_can_be_encrypted_globally_and_overridden(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            fn() => new InertiaConfig(history: new HistoryConfig(encrypt: true)),
        );

        $response = $this->http->get(
            uri: uri([TestController::class, 'encryptHistoryOverride']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertFalse($page['encryptHistory']);
    }

    public function test_the_history_can_be_cleared(): void
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

    public function test_the_history_can_be_cleared_when_redirecting(): void
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
}
