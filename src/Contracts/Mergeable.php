<?php

declare(strict_types=1);

namespace Inertia\Contracts;

interface Mergeable
{
    /**
     * Mark the property for merging.
     */
    public function merge(): static;

    /**
     * Determine if the property should be merged.
     */
    public function shouldMerge(): bool;

    /**
     * Determine if the property should be deep merged.
     */
    public function shouldDeepMerge(): bool;

    /**
     * Get the properties to match on for merging.
     *
     * @return array<int, string>
     */
    public function matchesOn(): array;
}
