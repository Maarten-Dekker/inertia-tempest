<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Response;
use Inertia\ResponseFactory;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class HelperTest extends TestCase
{
    #[Test]
    public function the_helper_function_returns_an_instance_of_the_response_factory(): void
    {
        $this->assertInstanceOf(ResponseFactory::class, inertia());
    }

    #[Test]
    public function the_helper_function_returns_a_response_instance(): void
    {
        $this->assertInstanceOf(Response::class, inertia('User/Edit', [
            'user' => ['name' => 'Jonathan'],
        ]));
    }

    #[Test]
    public function the_instance_is_the_same_as_the_facade_instance(): void
    {
        inertia()->share('key', 'value');

        $this->assertSame('value', inertia()->getShared('key'));
    }
}
