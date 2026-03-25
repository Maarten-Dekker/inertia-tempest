<?php

declare(strict_types=1);

namespace Inertia\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Inertia\Contracts\Arrayable;
use Inertia\Contracts\ProvidesScrollMetadata;
use InvalidArgumentException;
use Tempest\Support\Paginator\PaginatedData;

final readonly class ScrollMetadata implements Arrayable, ProvidesScrollMetadata
{
    /**
     * Create a new scroll metadata instance.
     */
    public function __construct(
        public string $pageName,
        public int|string|null $previousPage = null,
        public int|string|null $nextPage = null,
        public int|string|null $currentPage = null,
    ) {}

    /**
     * Create a scroll metadata instance from a Laravel or Tempest paginator.
     */
    public static function fromPaginator(mixed $paginator, string $pageName = 'page'): self
    {
        if ($paginator instanceof PaginatedData) {
            return new self(
                pageName: $pageName,
                previousPage: $paginator->previousPage,
                nextPage: $paginator->nextPage,
                currentPage: $paginator->currentPage,
            );
        }

        $paginatorClass = Paginator::class;
        $lengthAwarePaginatorClass = LengthAwarePaginator::class;

        if (
            class_exists($paginatorClass)
            && (is_a($paginator, $paginatorClass) || is_a($paginator, $lengthAwarePaginatorClass))
        ) {
            return new self(
                pageName: $pageName,
                previousPage: $paginator->currentPage() > 1 ? $paginator->currentPage() - 1 : null,
                nextPage: $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                currentPage: $paginator->currentPage(),
            );
        }

        throw new InvalidArgumentException('The given value is not a supported Tempest or Laravel paginator instance.');
    }

    /**
     * Convert the scroll metadata instance to an array.
     */
    public function toArray(): array
    {
        return [
            'pageName' => $this->pageName,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'currentPage' => $this->currentPage,
        ];
    }
}
