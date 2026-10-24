<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExtensibility;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Runs the {@see AbstractExtensibility} scenarios against SQL Server.
 */
#[Group('mssql')]
final class ExtensibilityTest extends AbstractExtensibility
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();

        parent::setUp();
    }
}
