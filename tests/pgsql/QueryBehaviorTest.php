<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\pgsql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractQueryBehavior;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

#[Group('pgsql')]
final class QueryBehaviorTest extends AbstractQueryBehavior
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::PGSQL->connection();

        parent::setUp();
    }
}
