<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractTreeTraversal;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Runs the {@see AbstractTreeTraversal} scenarios against SQL Server.
 */
#[Group('mssql')]
final class TreeTraversalTest extends AbstractTreeTraversal
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();

        parent::setUp();
    }
}
