<?php

declare(strict_types=1);

namespace Inertia\Support;

final readonly class Header
{
    /**
     * The main Inertia request header.
     */
    public const string INERTIA = 'x-inertia';

    /**
     * Header for specifying which error bag to use for validation errors.
     */
    public const string ERROR_BAG = 'x-inertia-error-bag';

    /**
     * Header for external redirects.
     */
    public const string LOCATION = 'x-inertia-location';

    /**
     * Header for the current asset version.
     */
    public const string VERSION = 'x-inertia-version';

    /**
     * Header specifying the component for partial reloads.
     */
    public const string PARTIAL_COMPONENT = 'x-inertia-partial-component';

    /**
     * Header specifying which props to include in partial reloads.
     */
    public const string PARTIAL_ONLY = 'x-inertia-partial-data';

    /**
     * Header specifying which props to exclude from partial reloads.
     */
    public const string PARTIAL_EXCEPT = 'x-inertia-partial-except';

    /**
     * Header for resetting the page state.
     */
    public const string RESET = 'x-inertia-reset';

    /**
     * Header for specifying the merge intent when paginating on infinite scroll.
     */
    public const string INFINITE_SCROLL_MERGE_INTENT = 'x-inertia-infinite-scroll-merge-intent';

    /**
     * Header specifying which once props to exclude from the response.
     */
    public const string EXCEPT_ONCE_PROPS = 'x-inertia-except-once-props';

    /**
     * Header for forwarded prefix.
     */
    public const string FORWARDED_PREFIX = 'x-forwarded-prefix';
}
