<?php

declare(strict_types=1);

namespace Inertia\Ssr\Commands;

use Inertia\Ssr\Contracts\Gateway;
use Inertia\Ssr\Contracts\HasHealthCheck;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class CheckSsr
{
    use HasConsole;

    public function __construct(
        private Gateway $gateway,
    ) {}

    /**
     * Check the Inertia SSR server health status.
     */
    #[ConsoleCommand(name: 'inertia:check-ssr', description: 'Check the Inertia SSR server health status')]
    public function __invoke(bool $silent = false): ExitCode|int
    {
        if (!($this->gateway instanceof HasHealthCheck)) {
            if (!$silent) {
                $this->console->error('The SSR gateway does not support health checks.');
            }

            return ExitCode::ERROR;
        }

        if ($this->gateway->isHealthy()) {
            if (!$silent) {
                $this->console->info('Inertia SSR server is running.');
            }

            return ExitCode::SUCCESS;
        }

        if (!$silent) {
            $this->console->error('Inertia SSR server is not running.');
        }

        return ExitCode::ERROR;
    }
}
