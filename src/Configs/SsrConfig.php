<?php

declare(strict_types=1);

namespace Inertia\Configs;

// @mago-ignore lint:no-redundant-use
use function Tempest\root_path;

/*
 * |--------------------------------------------------------------------------
 * | Server Side Rendering
 * |--------------------------------------------------------------------------
 * |
 * | These options configures if and how Inertia uses Server Side Rendering
 * | to pre-render the initial visits made to your application's pages.
 * |
 * | You can specify a custom SSR bundle path or omit it to let Inertia
 * | try and automatically detect it for you.
 * |
 * | Do note that enabling these options will NOT automatically make SSR work,
 * | as a separate rendering service needs to be available. For details,
 * | visit: https://inertiajs.com/server-side-rendering
 * |
 */
final class SsrConfig
{
    public bool $enabled;

    public string $url;

    public bool $ensure_bundle_exists;

    public function __construct(
        ?bool $enabled = null,
        ?string $url = null,
        ?bool $ensure_bundle_exists = null,
        public ?string $bundle = null,
    ) {
        $this->enabled = $enabled ?? false;
        $this->url = $url ?? 'http://127.0.0.1:13714';
        $this->ensure_bundle_exists = $ensure_bundle_exists ?? true;

        // $this->bundle = root_path('app/ssr/ssr.mjs');
    }
}
