<?php

declare(strict_types=1);

namespace Inertia\Ssr;

final readonly class Response
{
    /**
     * Create a new SSR response instance.
     */
    public function __construct(
        public string $head,
        public string $body,
    ) {}
}
