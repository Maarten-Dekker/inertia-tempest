<?php

declare(strict_types=1);

namespace Inertia\Support\Arr;

if (!function_exists(__NAMESPACE__ . '\forget_keys')) {
    /**
     * Removes the specified keys from the array using dot notation. The array is mutated.
     */
    function forget_keys(array &$array, string|int|array $keys): array
    {
        foreach ((array) $keys as $key) {
            $parts = explode('.', (string) $key);
            $target = &$array;

            foreach (array_slice($parts, 0, -1) as $part) {
                if (!isset($target[$part])) {
                    continue 2;
                }

                if (!is_array($target[$part])) {
                    continue 2;
                }

                $target = &$target[$part];
            }

            unset($target[end($parts)]);
        }

        return $array;
    }
}
