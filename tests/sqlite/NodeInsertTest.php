<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeInsert;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

#[Group('sqlite')]
final class NodeInsertTest extends AbstractNodeInsert
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::SQLITE->connection();

        parent::setUp();
    }
}
