<?php

declare(strict_types=1);

namespace Inertia\Configs;

/*
 * |--------------------------------------------------------------------------
 * | Main Inertia Configuration
 * |--------------------------------------------------------------------------
 * |
 * | This is the main configuration object for the Inertia package. It aggregates
 * | all the other configuration objects for easy access and management.
 * |
 * | Use this within your inertia.config.php file and override specific settings
 * | as needed.
 * |
 */
final readonly class InertiaConfig
{
    public function __construct(
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
        public SsrConfig $ssr = new SsrConfig(),

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
        public PageConfig $pages = new PageConfig(),

        /*
         * |--------------------------------------------------------------------------
         * | History Encryption
         * |--------------------------------------------------------------------------
         * |
         * | Inertia's history encryption protects privileged page data from being
         * | exposed via the browser's back button after logout. When enabled, it
         * | encrypts the current page's state using the browser's SubtleCrypto API
         * | before storing it in the history stack. The encryption key is saved
         * | in sessionStorage. On back navigation, the data is decrypted using
         * | this key. If the key has been cleared (e.g. via `clearHistory()`),
         * | decryption fails and Inertia fetches fresh data from the server.
         * |
         * | Note: Requires a secure context (HTTPS) due to usage of `crypto.subtle`.
         * | For details, visit: https://inertiajs.com/history-encryption
         * |
         */
        public HistoryConfig $history = new HistoryConfig(),

        /*
         * |--------------------------------------------------------------------------
         * | Validation Error Configuration
         * |--------------------------------------------------------------------------
         * |
         * | These options control how validation errors are formatted and presented
         * | in the Inertia 'errors' prop.
         * |
         */
        public ValidationConfig $validation = new ValidationConfig(),

        /*
         * |--------------------------------------------------------------------------
         * | Pagination Transformation
         * |--------------------------------------------------------------------------
         * |
         * | This option determines if Tempest's native paginator objects should be
         * | automatically transformed into the data/links/meta-structure that is
         * | standard in the Laravel ecosystem and expected by most Inertia.js
         * | front-end pagination components.
         * |
         */
        public bool $transform_pagination = false,
    ) {}
}
