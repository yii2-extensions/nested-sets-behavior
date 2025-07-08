<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractValidationAndStructure;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

#[Group('mysql')]
final class ValidationAndStructureTest extends AbstractValidationAndStructure
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MYSQL->connection();

        parent::setUp();
    }
}
