<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Tempest\Database\DatabaseSeeder;
use UnitEnum;

use function Tempest\Database\query;

final readonly class UserSeeder implements DatabaseSeeder
{
    public function run(null|string|UnitEnum $database): void
    {
        for ($i = 0; $i < 40; $i++) {
            query('users')->insert(name: "User {$i}")->execute();
        }
    }
}
