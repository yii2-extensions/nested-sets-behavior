<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractIntegrity;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Runs the {@see AbstractIntegrity} scenarios against SQLite.
 */
#[Group('sqlite')]
final class IntegrityTest extends AbstractIntegrity
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::SQLITE->connection();

        parent::setUp();
    }
}
