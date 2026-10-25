<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\oracle;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;
use yii2\extensions\nestedsets\tests\support\model\Tree;
use yii2\extensions\nestedsets\tests\TestCase;
use yii2\extensions\nestedsets\TreeLock;

/**
 * Unit tests for the {@see TreeLock} Oracle owner resolution when the table schema has no schema name.
 */
#[Group('oci')]
final class WriterLockTest extends TestCase
{
    public function testAcquireTreeLockResolvesOwnerFromSessionWhenTableSchemaHasNoSchemaName(): void
    {
        $db = $this->getDb();

        $schema = Tree::getTableSchema();

        $owner = $schema->schemaName;

        $behavior = (new Tree())->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            NestedSetsBehavior::class,
            $behavior,
            'Model must expose the nested sets behavior.',
        );

        $schema->schemaName = '';

        $outer = $db->beginTransaction();

        try {
            $behavior->acquireTreeLock();

            self::assertSame(
                2,
                (int) $db->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
                'Table resource must be registered once.',
            );
        } finally {
            $schema->schemaName = $owner;

            $outer->rollBack();
        }
    }

    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::ORACLE->connection();

        parent::setUp();

        $this->createDatabase();
    }
}
