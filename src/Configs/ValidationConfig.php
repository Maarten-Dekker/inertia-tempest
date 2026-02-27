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
    public bool $multiple_errors;

    public bool $localize_fields;

    public function __construct(?bool $multiple_errors = null, ?bool $localize_fields = null)
    {
        $this->multiple_errors = $multiple_errors ?? false;
        $this->localize_fields = $localize_fields ?? false;
    }
}
