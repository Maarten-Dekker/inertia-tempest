<?php

declare(strict_types=1);

use Inertia\Configs\HistoryConfig;
use Inertia\Configs\InertiaConfig;
use Inertia\Configs\PageConfig;
use Inertia\Configs\SsrConfig;

return new InertiaConfig(
    ssr: new SsrConfig(),
    pages: new PageConfig(),
    history: new HistoryConfig(),
    transform_pagination: false,
);
