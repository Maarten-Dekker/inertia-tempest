<?php

declare(strict_types=1);

namespace Inertia\Configs;

/*
 * --------------------------------------------------------------------------
 * History Encryption
 * --------------------------------------------------------------------------
 *
 * Enable `encrypt` to encrypt page data before it is stored in the
 * browser's history state, preventing sensitive information from
 * being accessible after logout. Can also be enabled per-request
 * or via the `inertia.encrypt` middleware.
 *
 * Note: Requires a secure context (HTTPS) due to usage of `crypto.subtle`.
 * For details, visit: https://inertiajs.com/history-encryption
 */
final class HistoryConfig
{
    public function __construct(
        public bool $encrypt = false,
    ) {}
}
