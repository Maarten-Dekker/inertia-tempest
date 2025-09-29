<?php

declare(strict_types=1);

namespace Inertia\Support;

use Inertia\Contracts\Arrayable;
use Override;
use Tempest\Http\Request;
use Tempest\Support\Paginator\PaginatedData;

use function Tempest\get;

class PaginatorAdapter implements Arrayable
{
    protected Request $request;

    public function __construct(
        protected PaginatedData $paginator,
    ) {
        $this->request = get(Request::class);
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'data' => $this->paginator->data,
            'links' => $this->buildLinks(),
            'meta' => $this->buildMeta(),
        ];
    }

    protected function buildLinks(): array
    {
        return [
            'first' => $this->buildUrlForPage(1),
            'last' => $this->buildUrlForPage($this->paginator->totalPages),
            'prev' => $this->paginator->previousPage ? $this->buildUrlForPage($this->paginator->previousPage) : null,
            'next' => $this->paginator->nextPage ? $this->buildUrlForPage($this->paginator->nextPage) : null,
        ];
    }

    protected function buildMeta(): array
    {
        $metaLinks = $this->buildMetaLinks();

        return [
            'current_page' => $this->paginator->currentPage,
            'from' => $this->paginator->offset + 1,
            'last_page' => $this->paginator->totalPages,
            'links' => $metaLinks,
            'path' => $this->request->path,
            'per_page' => $this->paginator->itemsPerPage,
            'to' => $this->paginator->offset + $this->paginator->count,
            'total' => $this->paginator->totalItems,
        ];
    }

    protected function buildMetaLinks(): array
    {
        $links = [];

        $links[] = [
            'url' => $this->paginator->previousPage ? $this->buildUrlForPage($this->paginator->previousPage) : null,
            'label' => '&laquo; Previous',
            'active' => false,
        ];

        foreach ($this->paginator->pageRange as $page) {
            $links[] = [
                'url' => $this->buildUrlForPage($page),
                'label' => (string) $page,
                'active' => $page === $this->paginator->currentPage,
            ];
        }

        $links[] = [
            'url' => $this->paginator->nextPage ? $this->buildUrlForPage($this->paginator->nextPage) : null,
            'label' => 'Next &raquo;',
            'active' => false,
        ];

        return $links;
    }

    protected function buildUrlForPage(int $page): string
    {
        $query = $this->request->query;
        $query['page'] = $page;

        return $this->request->path . '?' . http_build_query($query);
    }
}
