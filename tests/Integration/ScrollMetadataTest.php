<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Configs\InertiaConfig;
use Inertia\Support\PaginatorAdapter;
use Inertia\Support\ScrollMetadata;
use Inertia\Tests\Fixtures\User;
use Inertia\Tests\Fixtures\UserSeeder;
use Inertia\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Support\Paginator\PaginatedData;
use Tempest\Support\Paginator\Paginator;

final class ScrollMetadataTest extends TestCase
{
    private static bool $dbInitialized = false;

    private array $users;

    #[PreCondition]
    protected function configure(): void
    {
        if (! self::$dbInitialized) {
            $this->database->setup();
            new UserSeeder()->run(null);
            self::$dbInitialized = true;
        }

        $this->users = User::all();
    }

    #[Test]
    public function extract_metadata_from_tempest_paginator(): void
    {
        $this->assertSame(
            ['pageName' => 'page', 'previousPage' => null, 'nextPage' => 2, 'currentPage' => 1],
            ScrollMetadata::fromPaginator($this->paginate(1))->toArray(),
        );
        $this->assertSame(
            ['pageName' => 'page', 'previousPage' => 1, 'nextPage' => 3, 'currentPage' => 2],
            ScrollMetadata::fromPaginator($this->paginate(2))->toArray(),
        );
        $this->assertSame(
            ['pageName' => 'page', 'previousPage' => 2, 'nextPage' => null, 'currentPage' => 3],
            ScrollMetadata::fromPaginator($this->paginate(3))->toArray(),
        );
    }

    #[Test]
    public function extract_metadata_when_laravel_adapter_is_used(): void
    {
        $this->container->singleton(InertiaConfig::class, static fn () => new InertiaConfig(laravel_pagination: true));
        $mockRequest = $this->makeRequest(uri: '/users');

        $toLaravel = fn (int $page) => new PaginatorAdapter(
            $this->paginate($page),
            $mockRequest,
        )->toIlluminatePaginator();

        $this->assertSame(
            ['pageName' => 'page', 'previousPage' => null, 'nextPage' => 2, 'currentPage' => 1],
            ScrollMetadata::fromPaginator($toLaravel(1))->toArray(),
        );
        $this->assertSame(
            ['pageName' => 'page', 'previousPage' => 1, 'nextPage' => 3, 'currentPage' => 2],
            ScrollMetadata::fromPaginator($toLaravel(2))->toArray(),
        );
        $this->assertSame(
            ['pageName' => 'page', 'previousPage' => 2, 'nextPage' => null, 'currentPage' => 3],
            ScrollMetadata::fromPaginator($toLaravel(3))->toArray(),
        );
    }

    #[Test]
    public function throws_exception_if_not_a_paginator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given value is not a supported Tempest or Laravel paginator instance.');
        ScrollMetadata::fromPaginator($this->users);
    }

    private function paginate(int $currentPage): PaginatedData
    {
        return new Paginator(
            totalItems: count($this->users),
            itemsPerPage: 15,
            currentPage: $currentPage,
        )->paginate(array_slice($this->users, ($currentPage - 1) * 15, 15));
    }
}
