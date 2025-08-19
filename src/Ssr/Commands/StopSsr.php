<?php

declare(strict_types=1);

namespace Inertia\Ssr\Commands;

use Inertia\Configs\InertiaConfig;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\HttpClient\HttpClient;
use Throwable;

final readonly class StopSsr
{
    use HasConsole;

    public function __construct(
        private InertiaConfig $config,
        private HttpClient $client,
        private CheckSsr $checkSsrCommand,
    ) {}

    /**
     * Stop the Inertia SSR server.
     */
    #[ConsoleCommand(name: 'inertia:stop-ssr', description: 'Stop the Inertia SSR server')]
    public function __invoke(bool $silent = false): ExitCode|int
    {
        if (($this->checkSsrCommand)(silent: true)->value === ExitCode::ERROR->value) {
            if (!$silent) {
                $this->console->error("Inertia SSR server isn't running.");
            }

            return ExitCode::SUCCESS;
        }

        $url = rtrim($this->config->ssr->url, '/') . '/shutdown';

        /**
         * @mago-expect best-practices/no-empty-catch-clause
         */
        try {
            $this->client->post($url);
        } catch (Throwable) {
            // An error here means the server shut down very quickly.
        }

        for ($i = 0; $i < 10; $i++) {
            usleep(200000);

            if (($this->checkSsrCommand)(silent: true)->value === ExitCode::ERROR->value) {
                if (!$silent) {
                    $this->console->info('Inertia SSR server stopped successfully.');
                }

                return ExitCode::SUCCESS;
            }
        }

        if (!$silent) {
            $this->console->error('Failed to stop the Inertia SSR server.');
        }

        return ExitCode::ERROR;
    }
}
