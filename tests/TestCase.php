<?php

declare(strict_types=1);

namespace Inertia\Tests;

use Inertia\ResponseFactory;
use Mockery;
use Override;
use Tempest\Core\Application;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Framework\Testing\IntegrationTest;
use Tempest\Http\GenericRequest;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Router\HttpApplication;

abstract class TestCase extends IntegrationTest
{
    protected string $root = __DIR__ . '/../';

    protected ResponseFactory $factory;

    protected const array EXAMPLE_PAGE_OBJECT = [
        'component' => 'Foo/Bar',
        'props' => ['foo' => 'bar'],
        'url' => '/test',
        'version' => '',
        'encryptHistory' => false,
        'clearHistory' => false,
    ];

    #[Override]
    protected function setUp(): void
    {
        $this->discoveryLocations[] = new DiscoveryLocation(
            namespace: 'Inertia\\Tests\\Fixtures\\',
            path: __DIR__ . '/Fixtures',
        );

        parent::setUp();

        $this->actAsHttpApplication();

        $this->factory = $this->container->get(ResponseFactory::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function actAsHttpApplication(): HttpApplication
    {
        $application = new HttpApplication($this->container);

        $this->container->singleton(Application::class, fn() => $application);

        return $application;
    }

    /**
     * Set the current request in the container.
     */
    protected function makeRequest(
        string $uri = '/user/123',
        Method $method = Method::GET,
        array $headers = [],
    ): Request {
        $request = new GenericRequest(
            method: $method,
            uri: $uri,
            headers: $headers,
        );
        $this->container->singleton(Request::class, fn() => $request);

        return $request;
    }
}
