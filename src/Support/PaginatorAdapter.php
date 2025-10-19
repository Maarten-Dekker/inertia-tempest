<?php

declare(strict_types=1);

namespace Inertia\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Contracts\Arrayable;
use Override;
use Tempest\Http\Request;
use Tempest\Support\Paginator\PaginatedData;

final readonly class PaginatorAdapter implements Arrayable
{
    public function __construct(
        private PaginatedData $paginator,
        private Request $request,
    ) {}

    public function toIlluminatePaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: $this->paginator->data,
            total: $this->paginator->totalItems,
            perPage: $this->paginator->itemsPerPage,
            currentPage: $this->paginator->currentPage,
            options: [
                'path' => $this->request->path,
                'query' => $this->request->query,
            ],
        );
    }

    #[Override]
    public function toArray(): array
    {
        return $this->toIlluminatePaginator()->toArray();
    }
}
