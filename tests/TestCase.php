<?php

declare(strict_types=1);

namespace Inertia\Tests;

use Inertia\ResponseFactory;
use Mockery;
use Override;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Framework\Testing\IntegrationTest;
use Tempest\Http\GenericRequest;
use Tempest\Http\Method;
use Tempest\Http\Request;

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

    protected function setUp(): void
    {
        $this->discoveryLocations[] = new DiscoveryLocation(
            namespace: 'Inertia\\Tests\\Fixtures\\',
            path: __DIR__ . '/Fixtures',
        );

        parent::setUp();

        $this->factory = $this->container->get(ResponseFactory::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
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
        $this->container->singleton(Request::class, static fn () => $request);

        return $request;
    }
}
