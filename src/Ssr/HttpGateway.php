<?php

declare(strict_types=1);

namespace Inertia\Ssr;

use Exception;
use Inertia\Configs\InertiaConfig;
use Inertia\Ssr\Contracts\Gateway;
use Inertia\Ssr\Contracts\HasHealthCheck;
use Override;
use Tempest\HttpClient\HttpClient;
use Tempest\Support\Str;
use Throwable;

final readonly class HttpGateway implements Gateway, HasHealthCheck
{
    public function __construct(
        private InertiaConfig $config,
        private HttpClient $client,
        private BundleDetector $bundleDetector,
    ) {}

    /**
     * Dispatch the Inertia page to the SSR engine via HTTP.
     *
     * @param  array<string, mixed>  $page
     */
    #[Override]
    public function dispatch(array $page): ?Response
    {
        if (!$this->shouldDispatch()) {
            return null;
        }

        try {
            $response = $this->client->post(
                uri: $this->getUrl('/render'),
                headers: ['Content-Type' => 'application/json'],
                body: json_encode($page),
            );

            if (!$response->isSuccessful()) {
                throw new Exception('SSR request failed.');
            }

            $data = json_decode($response->getBody(), true);

            if (is_null($data)) {
                return null;
            }

            return new Response(implode("\n", $data['head']), $data['body']);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Determine if the SSR server is healthy.
     */
    #[Override]
    public function isHealthy(): bool
    {
        try {
            return $this
                ->client->get($this->getUrl('/health'))
                ->status->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Determine if the page should be dispatched to the SSR engine.
     */
    private function shouldDispatch(): bool
    {
        if (!$this->config->ssr->enabled) {
            return false;
        }

        if (!$this->config->ssr->ensure_bundle_exists) {
            return true;
        }

        return $this->bundleDetector->detect() !== null;
    }

    /**
     * Determine if the SSR feature is enabled.
     */
    private function ssrIsEnabled(): bool
    {
        return $this->config->ssr->enabled;
    }

    /**
     * Determine if dispatch should proceed without bundle detection.
     */
    private function shouldDispatchWithoutBundle(): bool
    {
        return !$this->config->ssr->ensure_bundle_exists;
    }

    /**
     * Check if an SSR bundle exists.
     */
    private function bundleExists(): bool
    {
        return new BundleDetector()->detect() !== null;
    }

    /**
     * Get the complete SSR URL by combining the base URL with the given path.
     */
    private function getUrl(string $path): string
    {
        $parts = parse_url($this->config->ssr->url);
        $baseUrl = "{$parts['scheme']}://{$parts['host']}:{$parts['port']}";

        return $baseUrl . Str\ensure_starts_with($path, '/');
    }
}
