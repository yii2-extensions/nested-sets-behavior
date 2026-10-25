<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use yii\base\InvalidConfigException;
use yii\db\Exception as DatabaseException;
use yii\db\mssql\TableSchema;
use yii2\extensions\nestedsets\exception\Message;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\{DatabaseConnection, RewritingConnection};
use yii2\extensions\nestedsets\tests\support\model\Tree;
use yii2\extensions\nestedsets\tests\TestCase;
use yii2\extensions\nestedsets\TreeLock;

/**
 * Unit tests for the {@see TreeLock} SQL Server catalog resolution, lock timeout reading and rollback recovery.
 */
#[Group('mssql')]
final class WriterLockTest extends TestCase
{
    public function testAcquireTreeLockResolvesCatalogFromServerWhenTableSchemaHasNoCatalog(): void
    {
        $db = $this->getDb();
        $schema = $this->tableSchema();

        $catalog = $schema->catalogName;

        $behavior = $this->behavior();

        $schema->catalogName = '';

        $outer = $db->beginTransaction();

        try {
            $behavior->acquireTreeLock();

            self::assertSame(
                2,
                (int) $db->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
                'Table resource must be registered once.',
            );
        } finally {
            $schema->catalogName = $catalog;

            $outer->rollBack();
        }
    }

    public function testAcquireTreeLockUsesCatalogFromTableSchema(): void
    {
        $db = $this->getDb();
        $schema = $this->tableSchema();

        $catalog = $schema->catalogName;

        $behavior = $this->behavior();
        $current = $db
            ->createCommand('SELECT DB_NAME()')
            ->queryScalar();

        self::assertIsString(
            $current,
            'Current database name must be resolved.',
        );

        $schema->catalogName = $current;

        $outer = $db->beginTransaction();

        try {
            $behavior->acquireTreeLock();

            self::assertSame(
                2,
                (int) $db->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
                'Table resource must be registered once.',
            );
        } finally {
            $schema->catalogName = $catalog;

            $outer->rollBack();
        }
    }

    public function testRollBackDiscardsNativeTransactionAfterDeadlockError(): void
    {
        $db = $this->getDb();

        $outer = $db->beginTransaction();

        $cause = new DatabaseException('Transaction was deadlocked', ['40001', 1205, 'Transaction was deadlocked']);

        TreeLock::rollBack($db, $outer, $cause);

        self::assertNull(
            $db->getTransaction(),
            'Yii transaction must be closed.',
        );
        self::assertFalse(
            $db->getMasterPdo()->inTransaction(),
            'Native transaction must be closed.',
        );
        self::assertTrue(
            (new Tree(['name' => 'root']))->makeRoot(),
            'Connection must accept new writes.',
        );
    }

    public function testRollBackSkipsRecoveryWhenNativeTransactionIsAlreadyClosed(): void
    {
        $db = $this->getDb();

        $outer = $db->beginTransaction();

        $db->getMasterPdo()->rollBack();

        TreeLock::rollBack($db, $outer, new RuntimeException('cause'));

        self::assertNull(
            $db->getTransaction(),
            'Yii transaction must be closed.',
        );
        self::assertFalse(
            $db->getMasterPdo()->inTransaction(),
            'Native transaction must stay closed.',
        );
        self::assertTrue(
            (new Tree(['name' => 'root']))->makeRoot(),
            'Connection must accept new writes.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenCatalogIsNotAString(): void
    {
        $db = $this->rewriting();
        $schema = $this->tableSchema();

        $catalog = $schema->catalogName;

        $behavior = $this->behavior();

        $schema->catalogName = '';
        $db->exact = ['SELECT DB_NAME()' => 'SELECT NULL'];

        $outer = $db->beginTransaction();

        try {
            $behavior->acquireTreeLock();

            self::fail(
                'An unresolved catalog must be rejected.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                Message::CANNOT_RESOLVE_SQLSRV_DATABASE->getMessage(),
                $exception->getMessage(),
                'Unresolved catalog must be reported.',
            );
            self::assertSame(
                1,
                (int) $db->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
                'No resource may be registered.',
            );
        } finally {
            $db->exact = [];
            $schema->catalogName = $catalog;

            $outer->rollBack();
        }
    }

    public function testThrowInvalidConfigExceptionWhenLockTimeoutIsNotNumeric(): void
    {
        $db = $this->rewriting();
        $behavior = $this->behavior();

        $db->exact = ['SELECT @@LOCK_TIMEOUT' => "SELECT 'unavailable'"];

        $outer = $db->beginTransaction();

        try {
            $behavior->acquireTreeLock();

            self::fail(
                'An unreadable lock timeout must be rejected.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                Message::CANNOT_READ_SQLSRV_LOCK_TIMEOUT->getMessage(),
                $exception->getMessage(),
                'Unreadable lock timeout must be reported.',
            );
            self::assertSame(
                1,
                (int) $db->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
                'No resource may be registered.',
            );
        } finally {
            $db->exact = [];

            $outer->rollBack();
        }
    }

    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();
        $this->connection['class'] = RewritingConnection::class;

        parent::setUp();

        $this->createDatabase();
    }

    /**
     * @return NestedSetsBehavior<Tree>
     */
    private function behavior(): NestedSetsBehavior
    {
        $behavior = (new Tree())->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            NestedSetsBehavior::class,
            $behavior,
            'Model must expose the nested sets behavior.',
        );

        return $behavior;
    }

    private function rewriting(): RewritingConnection
    {
        $db = $this->getDb();

        self::assertInstanceOf(
            RewritingConnection::class,
            $db,
            'Connection must rewrite statements.',
        );

        return $db;
    }

    private function tableSchema(): TableSchema
    {
        $schema = Tree::getTableSchema();

        self::assertInstanceOf(
            TableSchema::class,
            $schema,
            'Table schema must be a SQL Server schema.',
        );

        return $schema;
    }
}
