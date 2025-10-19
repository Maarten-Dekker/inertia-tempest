<?php

declare(strict_types=1);

namespace Inertia\Support;

use Illuminate\Pagination\CursorPaginator;
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
    public static function fromPaginator(mixed $paginator): self
    {
        if ($paginator instanceof PaginatedData) {
            return new self('page', $paginator->previousPage, $paginator->nextPage, $paginator->currentPage);
        }

        if ($paginator instanceof CursorPaginator) {
            return new self(
                $cursorName = $paginator->getCursorName(),
                $paginator->previousCursor()?->encode(),
                $paginator->nextCursor()?->encode(),
                $paginator->onFirstPage() ? 1 : CursorPaginator::resolveCurrentCursor($cursorName)?->encode() ?? 1,
            );
        }

        if ($paginator instanceof LengthAwarePaginator || $paginator instanceof Paginator) {
            return new self(
                $paginator->getPageName(),
                $paginator->currentPage() > 1 ? $paginator->currentPage() - 1 : null,
                $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                $paginator->currentPage(),
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
