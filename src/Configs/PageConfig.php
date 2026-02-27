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
 *
 * By default, the initial page data is passed via a script element.
 * Set `use_data_attribute_for_initial_page` to true to use a data
 * attribute on the root element instead.
 */
final class PageConfig
{
    public bool $ensure_pages_exists;

    public array $paths;

    public array $extensions;

    public bool $use_data_attribute_for_initial_page;

    public function __construct(
        ?bool $ensure_pages_exists = null,
        ?array $paths = null,
        ?array $extensions = null,
        ?bool $use_data_attribute_for_initial_page = null,
    ) {
        $this->ensure_pages_exists = $ensure_pages_exists ?? false;
        $this->paths = $paths ?? [root_path('app/')];
        $this->extensions = $extensions ?? [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ];
        $this->use_data_attribute_for_initial_page = $use_data_attribute_for_initial_page ?? false;
    }
}
