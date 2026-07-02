<?php

declare(strict_types=1);

namespace Inertia\Contracts;

interface ProvidesScrollMetadata
{
    public string $pageName { get; }

    public int|string|null $previousPage { get; }

    public int|string|null $nextPage { get; }

    public int|string|null $currentPage { get; }
}
