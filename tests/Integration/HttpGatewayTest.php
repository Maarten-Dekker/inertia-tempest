<?php

declare(strict_types=1);

use Inertia\Configs\InertiaConfig;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response as SsrResponse;
use Inertia\Tests\Fixtures\FakeClientResponse;
use Inertia\Tests\TestCase;
use Tempest\HttpClient\HttpClient;

use function Tempest\root_path;

class HttpGatewayTest extends TestCase
{
    public function test_it_returns_null_when_ssr_is_disabled(): void
    {
        $config = $this->container->get(InertiaConfig::class);
        $originalValue = $config->ssr->enabled;
        $config->ssr->enabled = false;

        $gateway = $this->container->get(HttpGateway::class);

        try {
            $this->assertNotInstanceOf(\Inertia\Ssr\Response::class, $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));
        } finally {
            $config->ssr->enabled = $originalValue;
        }
    }

    public function test_it_returns_null_when_no_bundle_file_is_detected(): void
    {
        $config = $this->container->get(InertiaConfig::class);
        $originalSsrEnabled = $config->ssr->enabled;
        $originalSsrBundle = $config->ssr->bundle;
        $config->ssr->enabled = true;
        $config->ssr->bundle = null;

        $gateway = $this->container->get(HttpGateway::class);

        try {
            $this->assertNotInstanceOf(\Inertia\Ssr\Response::class, $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));
        } finally {
            $config->ssr->enabled = $originalSsrEnabled;
            $config->ssr->bundle = $originalSsrBundle;
        }
    }

    public function test_it_dispatches_the_page_when_bundle_is_detected(): void
    {
        $bundlePath = root_path('temp-ssr-bundle.js');
        touch($bundlePath);

        $config = $this->container->get(InertiaConfig::class);
        $originalSsrEnabled = $config->ssr->enabled;
        $originalSsrBundle = $config->ssr->bundle;
        $config->ssr->enabled = true;
        $config->ssr->bundle = $bundlePath;

        $fakeResponse = new FakeClientResponse(
            body: json_encode([
                'head' => ['<title>SSR Test</title>', '<style></style>'],
                'body' => '<div id="app">SSR Response</div>',
            ]),
            isSuccess: true,
        );

        $mockClient = Mockery::mock(HttpClient::class)
            ->shouldReceive('post')
            ->once()
            ->andReturn($fakeResponse)
            ->getMock();

        $this->container->singleton(HttpClient::class, fn() => $mockClient);

        try {
            $gateway = $this->container->get(HttpGateway::class);
            $response = $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);

            $this->assertInstanceOf(SsrResponse::class, $response);
            $this->assertSame("<title>SSR Test</title>\n<style></style>", $response->head);
            $this->assertSame('<div id="app">SSR Response</div>', $response->body);
        } finally {
            unlink($bundlePath);
            $config->ssr->enabled = $originalSsrEnabled;
            $config->ssr->bundle = $originalSsrBundle;
        }
    }

    public function test_it_returns_null_when_the_http_request_fails(): void
    {
        $bundlePath = root_path('temp-ssr-bundle.js');
        touch($bundlePath);

        $config = $this->container->get(InertiaConfig::class);
        $originalSsrEnabled = $config->ssr->enabled;
        $originalSsrBundle = $config->ssr->bundle;
        $config->ssr->enabled = true;
        $config->ssr->bundle = $bundlePath;

        $fakeResponse = new FakeClientResponse(
            body: '',
            isSuccess: false,
        );

        $mockClient = Mockery::mock(HttpClient::class)
            ->shouldReceive('post')
            ->once()
            ->andReturn($fakeResponse)
            ->getMock();

        $this->container->singleton(HttpClient::class, fn() => $mockClient);

        try {
            $gateway = $this->container->get(HttpGateway::class);
            $response = $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);

            $this->assertNotInstanceOf(\Inertia\Ssr\Response::class, $response);
        } finally {
            unlink($bundlePath);
            $config->ssr->enabled = $originalSsrEnabled;
            $config->ssr->bundle = $originalSsrBundle;
        }
    }

    public function test_it_returns_null_when_invalid_json_is_returned(): void
    {
        $bundlePath = root_path('temp-ssr-bundle.js');
        touch($bundlePath);

        $config = $this->container->get(InertiaConfig::class);
        $originalSsrEnabled = $config->ssr->enabled;
        $originalSsrBundle = $config->ssr->bundle;
        $config->ssr->enabled = true;
        $config->ssr->bundle = $bundlePath;

        $fakeResponse = new FakeClientResponse(
            body: 'invalid json',
            isSuccess: true,
        );

        $mockClient = Mockery::mock(HttpClient::class)
            ->shouldReceive('post')
            ->once()
            ->andReturn($fakeResponse)
            ->getMock();

        $this->container->singleton(HttpClient::class, fn() => $mockClient);

        try {
            $gateway = $this->container->get(HttpGateway::class);
            $response = $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);

            $this->assertNotInstanceOf(\Inertia\Ssr\Response::class, $response);
        } finally {
            unlink($bundlePath);
            $config->ssr->enabled = $originalSsrEnabled;
            $config->ssr->bundle = $originalSsrBundle;
        }
    }

    public function test_health_check_the_ssr_server(): void
    {
        $successResponse = new FakeClientResponse(
            body: '',
            isSuccess: true,
        );
        $failureResponse = new FakeClientResponse(
            body: '',
            isSuccess: false,
        );

        $mockClient = Mockery::mock(HttpClient::class)
            ->shouldReceive('get')
            ->times(2)
            ->andReturn($successResponse, $failureResponse)
            ->getMock();

        $this->container->singleton(HttpClient::class, fn() => $mockClient);

        $gateway = $this->container->get(HttpGateway::class);
        $this->assertInstanceOf(\Inertia\Ssr\HttpGateway::class, $gateway);

        $this->assertTrue($gateway->isHealthy());
        $this->assertFalse($gateway->isHealthy());
    }
}
