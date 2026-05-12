<?php

declare(strict_types=1);

namespace Inertia\Configs;

/*
 *--------------------------------------------------------------------------
 * Validation Error Configuration
 *--------------------------------------------------------------------------
 *
 * These options control how validation errors are formatted and presented
 * in the Inertia 'errors' prop.
 *
 */
final class ValidationConfig
{
    public function __construct(
        public bool $multiple_errors = false,
        public bool $localize_fields = false,
    ) {}
}
