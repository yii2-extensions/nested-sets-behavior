<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractCacheManagement;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Runs the {@see AbstractCacheManagement} scenarios against SQL Server.
 */
#[Group('mssql')]
final class CacheManagementTest extends AbstractCacheManagement
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();

        parent::setUp();
    }
}
