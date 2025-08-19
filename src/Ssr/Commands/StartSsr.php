<?php

declare(strict_types=1);

namespace Inertia\Ssr\Commands;

use Inertia\Configs\InertiaConfig;
use Inertia\Ssr\BundleDetector;
use Inertia\Ssr\Exceptions\SsrException;
use Symfony\Component\Process\Process;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class StartSsr
{
    use HasConsole;

    public function __construct(
        private InertiaConfig $config,
        private BundleDetector $bundleDetector,
        private StopSsr $stopSsrCommand,
    ) {}

    /**
     * Start the Inertia SSR server.
     */
    #[ConsoleCommand(name: 'inertia:start-ssr', description: 'Start the Inertia SSR server')]
    public function __invoke(string $runtime = 'node'): ExitCode|int
    {
        if (!$this->config->ssr->enabled) {
            $this->console->error('Inertia SSR is not enabled. Enable it via the `inertia.ssr.enabled` config option.');

            return ExitCode::ERROR;
        }

        $bundle = $this->bundleDetector->detect();
        $configuredBundle = $this->config->ssr->bundle;

        if ($bundle === null) {
            $this->console->error(
                $configuredBundle
                    ? ('Inertia SSR bundle not found at the configured path: "' . $configuredBundle . '"')
                    : 'Inertia SSR bundle not found. Set the correct Inertia SSR bundle path in your `inertia.ssr.bundle` config.',
            );

            return ExitCode::ERROR;
        }

        if ($configuredBundle && $bundle !== $configuredBundle) {
            $this->console->warning('Inertia SSR bundle not found at the configured path: "' . $configuredBundle . '"');
            $this->console->warning('Using a default bundle instead: "' . $bundle . '"');
        }

        if (!in_array($runtime, ['node', 'bun'], true)) {
            $this->console->error('Unsupported runtime: "' . $runtime . '". Supported runtimes are `node` and `bun`.');

            return ExitCode::ERROR;
        }

        ($this->stopSsrCommand)(silent: true);

        $process = new Process([$runtime, $bundle]);
        $process->setTimeout(null);
        $process->start();

        if (extension_loaded('pcntl')) {
            $stop = function () use ($process): void {
                $process->stop();
            };
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, $stop);
            pcntl_signal(SIGQUIT, $stop);
            pcntl_signal(SIGTERM, $stop);
        }

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $this->console->info(trim($data));
            } else {
                $this->console->error(trim($data));
                new SsrException($data);
            }
        }

        return ExitCode::SUCCESS;
    }
}
