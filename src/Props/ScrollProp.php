<?php

declare(strict_types=1);

namespace Inertia\Props;

use Inertia\Contracts\Deferrable;
use Inertia\Contracts\InvokableProp;
use Inertia\Contracts\Mergeable;
use Inertia\Contracts\ProvidesScrollMetadata;
use Inertia\Support\Header;
use Inertia\Support\ScrollMetadata;
use Inertia\Traits\DefersProps;
use Inertia\Traits\MergesProps;
use Inertia\Traits\ResolvesCallables;
use Override;
use Tempest\Http\Request;

use function Tempest\get;

/**
 * Represents a paginated property that can be merged during partial reloads.
 *
 * This class provides functionality for handling pagination data with merge capabilities,
 * allowing paginated content to be appended or prepended during client-side navigation.
 */
final class ScrollProp implements Deferrable, Mergeable, InvokableProp
{
    use DefersProps;
    use MergesProps;
    use ResolvesCallables;

    /**
     * The resolved property value.
     */
    private mixed $resolved;

    /**
     * The scroll metadata provider.
     *
     * @var ProvidesScrollMetadata|callable|null
     */
    private mixed $metadata;

    /**
     * Create a new merge property instance. Merge properties are combined
     * with existing client-side data during partial reloads instead of
     * completely replacing the property value.
     */
    public function __construct(
        private readonly mixed $value,
        private readonly string $pageName = 'page',
        private readonly string $wrapper = 'data',
        ProvidesScrollMetadata|callable|null $metadata = null,
    ) {
        $this->merge = true;
        $this->metadata = $metadata;
    }

    private Request $request {
        get => $this->request ??= get(Request::class);
    }

    /**
     * Configure the merge strategy based on the infinite scroll merge intent header.
     *
     * The frontend InfiniteScroll component sends its merge intent directly,
     * eliminating the need for direction-based logic on the backend.
     */
    public function configureMergeIntent(): ScrollProp
    {
        return $this->request->headers->get(Header::INFINITE_SCROLL_MERGE_INTENT) === 'prepend'
            ? $this->prepend($this->wrapper)
            : $this->append($this->wrapper);
    }

    /**
     * Get the pagination meta-information.
     *
     * @return array{pageName: string, previousPage: int|string|null, nextPage: int|string|null, currentPage: int|string|null}
     */
    public function metadata(): array
    {
        $provider = $this->resolveMetadataProvider();

        if ($provider instanceof ScrollMetadata) {
            return $provider->toArray();
        }

        return [
            'pageName' => $provider->pageName,
            'previousPage' => $provider->previousPage,
            'nextPage' => $provider->nextPage,
            'currentPage' => $provider->currentPage,
        ];
    }

    /**
     * Resolve the property value.
     */
    #[Override]
    public function __invoke(): mixed
    {
        return $this->resolved ?? ($this->resolved = $this->resolveCallable($this->value));
    }

    /**
     * Resolve the scroll metadata provider.
     */
    private function resolveMetadataProvider(): ProvidesScrollMetadata
    {
        if ($this->metadata instanceof ProvidesScrollMetadata) {
            return $this->metadata;
        }

        $value = $this();

        if (is_null($this->metadata)) {
            return ScrollMetadata::fromPaginator(
                paginator: $value,
                pageName: $this->pageName,
            );
        }

        return ($this->metadata)($value);
    }
}
