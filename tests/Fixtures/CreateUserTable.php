<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CreateTableStatement;

final class CreateUserTable implements MigratesUp
{
    public string $name = 'create_user_table';

    public function up(): QueryStatement
    {
        return new CreateTableStatement('users')
            ->primary()
            ->string('name');
    }
}
