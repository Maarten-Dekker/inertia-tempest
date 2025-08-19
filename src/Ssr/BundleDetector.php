<?php

declare(strict_types=1);

namespace Inertia\Ssr;

use Inertia\Configs\InertiaConfig;

use function Tempest\get;
use function Tempest\root_path;

final class BundleDetector
{
    private InertiaConfig $config {
        get => get(InertiaConfig::class);
    }

    /**
     * Detect and return the path to the SSR bundle file.
     */
    public function detect(): ?string
    {
        $potentialPaths = [
            $this->config->ssr->bundle,
            root_path('ssr/inertia.ssr.mjs'),
            root_path('ssr/inertia.ssr.js'),
        ];

        foreach ($potentialPaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
