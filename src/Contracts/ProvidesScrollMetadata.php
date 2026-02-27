<?php

declare(strict_types=1);

namespace Inertia\Contracts;

interface ProvidesScrollMetadata
{
    public string $pageName { get; }

    public int|null|string $previousPage { get; }

    public int|null|string $nextPage { get; }

    public int|null|string $currentPage { get; }
}
