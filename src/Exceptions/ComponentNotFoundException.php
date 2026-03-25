<?php

declare(strict_types=1);

namespace Inertia\Exceptions;

use Exception;
use Override;
use Tempest\Core\ProvidesContext;

final class ComponentNotFoundException extends Exception implements ProvidesContext
{
    public function __construct(
        private readonly string $component,
        private readonly array $searchedPaths,
    ) {
        $paths = implode(', ', $this->searchedPaths);
        $message = sprintf('Inertia page component [%s] not found. Searched in paths: %s', $this->component, $paths);

        parent::__construct($message);
    }

    #[Override]
    public function context(): array
    {
        return [
            'component' => $this->component,
            'searched_paths' => $this->searchedPaths,
        ];
    }
}
