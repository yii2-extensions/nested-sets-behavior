<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\pgsql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractConcurrency;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Runs the {@see AbstractConcurrency} scenarios against PostgreSQL.
 */
#[Group('pgsql')]
final class ConcurrencyTest extends AbstractConcurrency
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::PGSQL->connection();

        parent::setUp();
    }
}
