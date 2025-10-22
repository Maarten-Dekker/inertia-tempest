<?php

declare(strict_types=1);

use Inertia\Response;
use Inertia\ResponseFactory;
use Tempest\Support\Arr\ArrayInterface;

use function Tempest\get;

if (!function_exists('inertia')) {
    function inertia(?string $component = null, array|ArrayInterface $props = []): Response|ResponseFactory
    {
        $instance = get(ResponseFactory::class);

        if ($component) {
            return $instance->render($component, $props);
        }

        return $instance;
    }
}

if (!function_exists('inertia_location')) {
    function inertia_location(string $url): Response
    {
        return get(ResponseFactory::class)->location($url);
    }
}
