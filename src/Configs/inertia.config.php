<?php

declare(strict_types=1);

use Inertia\Configs\HistoryConfig;
use Inertia\Configs\InertiaConfig;
use Inertia\Configs\PageConfig;
use Inertia\Configs\SsrConfig;
use Inertia\Configs\ValidationConfig;

return new InertiaConfig(
    ssr: new SsrConfig(),
    pages: new PageConfig(),
    history: new HistoryConfig(),
    validation: new ValidationConfig(),
    transform_pagination: false,
);
