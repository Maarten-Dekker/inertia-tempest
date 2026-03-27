<?php

declare(strict_types=1);

namespace Inertia\Configs;

use function Tempest\root_path;

/*
 *--------------------------------------------------------------------------
 * Pages
 *--------------------------------------------------------------------------
 *
 * Set `ensure_pages_exist` to true if you want to enforce that Inertia page
 * components exist on disk when rendering a page. This is useful for
 * catching missing or misnamed components. Not recommended for
 * production use.
 *
 * The `paths` and `extensions ` options define where to look
 * for page components and which file extensions to consider.
 */
final class PageConfig
{
    public bool $ensure_pages_exist;

    public array $paths;

    public array $extensions;

    public function __construct(?bool $ensure_pages_exist = null, ?array $paths = null, ?array $extensions = null)
    {
        $this->ensure_pages_exist = $ensure_pages_exist ?? false;
        $this->paths = $paths ?? [root_path('app/')];
        $this->extensions = $extensions ?? [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ];
    }
}
