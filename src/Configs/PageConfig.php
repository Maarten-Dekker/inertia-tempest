<?php

declare(strict_types=1);

namespace Inertia\Configs;

use function Tempest\root_path;

/*
 * |--------------------------------------------------------------------------
 * | Pages
 * |--------------------------------------------------------------------------
 * |
 * | Set `ensure_pages_exist` to true if you want to enforce that Inertia page
 * | components exist on disk when rendering a page. This is useful for
 * | catching missing or misnamed components.
 * |
 * | Not recommended for production use, as it introduces filesystem overhead
 * | on every request and may impact performance.
 * |
 * | The `page_paths` and `page_extensions` options define where to look
 * | for page components and which file extensions to consider. For
 * | details, visit: https://inertiajs.com/pages#creating-pages
 * |
 */
final class PageConfig
{
    public bool $ensure_pages_exists;

    public array $page_paths;

    public array $page_extensions;

    public bool $use_script_element_for_initial_page;

    public function __construct(
        ?bool $ensure_pages_exists = null,
        ?array $page_paths = null,
        ?array $page_extensions = null,
        ?bool $use_script_element_for_initial_page = null,
    ) {
        $this->ensure_pages_exists = $ensure_pages_exists ?? false;
        $this->page_paths = $page_paths ?? [root_path('app/')];
        $this->page_extensions = $page_extensions ?? [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ];
        $this->use_script_element_for_initial_page = $use_script_element_for_initial_page ?? false;
    }
}
