<?php

declare(strict_types=1);

namespace Inertia\Support;

use Inertia\Contracts\Arrayable;
use Override;
use RuntimeException;
use Tempest\Http\Request;
use Tempest\Support\Paginator\PaginatedData;

final readonly class PaginatorAdapter implements Arrayable
{
    public function __construct(
        private PaginatedData $paginator,
        private Request $request,
    ) {}

    public function toIlluminatePaginator(): object
    {
        $laravelPaginatorClass = \Illuminate\Pagination\LengthAwarePaginator::class;

        if (!class_exists($laravelPaginatorClass)) {
            throw new RuntimeException(
                'Cannot transform to Laravel paginator: package "illuminate/pagination" is not installed.',
            );
        }

        return new $laravelPaginatorClass(
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
