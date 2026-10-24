<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractConcurrency;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Runs the {@see AbstractConcurrency} scenarios against MySQL.
 */
#[Group('mysql')]
final class ConcurrencyTest extends AbstractConcurrency
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MYSQL->connection();

        parent::setUp();
    }

    #[Group('mutation')]
    public function testOlderOuterSnapshotCannotCommitASecondRoot(): void
    {
        parent::testOlderOuterSnapshotCannotCommitASecondRoot();
    }
}
