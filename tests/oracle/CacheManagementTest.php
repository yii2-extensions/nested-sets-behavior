<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\oracle;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractCacheManagement;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

#[Group('oci')]
final class CacheManagementTest extends AbstractCacheManagement
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::ORACLE->connection();

        parent::setUp();
    }
}
