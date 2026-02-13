<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Configs\InertiaConfig;
use Inertia\Support\PaginatorAdapter;
use Inertia\Support\ScrollMetadata;
use Inertia\Tests\Fixtures\User;
use Inertia\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Support\Paginator\Paginator;

final class ScrollMetadataTest extends TestCase
{
    private array $users;

    #[PreCondition]
    protected function configure(): void
    {
        $this->users = User::all();
    }

    #[Test]
    public function extract_metadata_from_tempest_paginator(): void
    {
        $this->assertCount(40, $this->users);

        $usersPage1 = new Paginator(
            totalItems: count($this->users),
            itemsPerPage: 15,
            currentPage: 1,
        )->paginate(array_slice($this->users, 0, 15));

        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
            ],
            ScrollMetadata::fromPaginator($usersPage1)->toArray(),
        );

        $usersPage2 = new Paginator(
            totalItems: count($this->users),
            itemsPerPage: 15,
            currentPage: 2,
        )->paginate(array_slice($this->users, 15, 15));

        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => 1,
                'nextPage' => 3,
                'currentPage' => 2,
            ],
            ScrollMetadata::fromPaginator($usersPage2)->toArray(),
        );

        $usersPage3 = new Paginator(
            totalItems: count($this->users),
            itemsPerPage: 15,
            currentPage: 3,
        )->paginate(array_slice($this->users, 30, 15));

        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => 2,
                'nextPage' => null,
                'currentPage' => 3,
            ],
            ScrollMetadata::fromPaginator($usersPage3)->toArray(),
        );
    }

    #[Test]
    public function extract_metadata_when_laravel_adapter_is_used(): void
    {
        $this->container->singleton(InertiaConfig::class, static fn () => new InertiaConfig(laravel_pagination: true));

        $mockRequest = $this->makeRequest(uri: '/users');

        $tempestPaginator1 = new Paginator(
            totalItems: 40,
            itemsPerPage: 15,
            currentPage: 1,
        )->paginate(array_slice($this->users, 0, 15));

        $laravelPaginator1 = new PaginatorAdapter($tempestPaginator1, $mockRequest)->toIlluminatePaginator();

        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
            ],
            ScrollMetadata::fromPaginator($laravelPaginator1)->toArray(),
        );

        $tempestPaginator2 = new Paginator(
            totalItems: 40,
            itemsPerPage: 15,
            currentPage: 2,
        )->paginate(array_slice($this->users, 15, 15));

        $laravelPaginator2 = new PaginatorAdapter($tempestPaginator2, $mockRequest)->toIlluminatePaginator();

        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => 1,
                'nextPage' => 3,
                'currentPage' => 2,
            ],
            ScrollMetadata::fromPaginator($laravelPaginator2)->toArray(),
        );

        $tempestPaginator3 = new Paginator(
            totalItems: 40,
            itemsPerPage: 15,
            currentPage: 3,
        )->paginate(array_slice($this->users, 30, 10));

        $laravelPaginator3 = new PaginatorAdapter($tempestPaginator3, $mockRequest)->toIlluminatePaginator();

        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => 2,
                'nextPage' => null,
                'currentPage' => 3,
            ],
            ScrollMetadata::fromPaginator($laravelPaginator3)->toArray(),
        );
    }

    #[Test]
    public function throws_exception_if_not_a_paginator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given value is not a supported Tempest or Laravel paginator instance.');

        ScrollMetadata::fromPaginator($this->users);
    }
}
