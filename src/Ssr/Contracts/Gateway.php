<?php

declare(strict_types=1);

namespace Inertia\Ssr\Contracts;

use Inertia\Ssr\Response;

interface Gateway
{
    /**
     * Dispatch the Inertia page to the SSR engine.
     *
     * @param  array<string, mixed>  $page
     */
    public function dispatch(array $page): ?Response;
}
