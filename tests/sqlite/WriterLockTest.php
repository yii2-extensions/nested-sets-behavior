<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PDO;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use RuntimeException;
use Throwable;
use Yii;
use yii\base\{InvalidConfigException, NotSupportedException};
use yii\caching\ArrayCache;
use yii\db\{Connection, Exception};
use yii\log\Logger;
use yii2\extensions\nestedsets\exception\Message;
use yii2\extensions\nestedsets\{NestedSetsBehavior, TreeLock};
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;
use yii2\extensions\nestedsets\tests\support\model\{ConcurrencyTree, NonTransactionalTree, Tree};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Unit tests for the {@see NestedSetsBehavior} writer lock settings, current reads and rollback recovery on SQLite.
 */
#[Group('sqlite')]
final class WriterLockTest extends TestCase
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::SQLITE->connection();

        parent::setUp();

        $this->createDatabase();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testBusyTimeoutIsAppliedInMillisecondsAndRestoredAfterSuccessAndFailure(bool $stringify): void
    {
        $db = $this->getDb();

        $db
            ->getMasterPdo()
            ->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);
        $db
            ->createCommand('PRAGMA busy_timeout = 23')
            ->execute();
        $db
            ->createCommand('CREATE TABLE observed_waits (timeout INTEGER NOT NULL)')
            ->execute();
        $db
            ->getMasterPdo()
            ->exec(
                'CREATE TRIGGER capture_guard BEFORE UPDATE ON nested_sets_lock BEGIN INSERT INTO observed_waits SELECT timeout FROM pragma_busy_timeout; END',
            );

        $behavior = $this->behavior();

        $behavior->lockTimeout = 1;

        $outer = $db->beginTransaction();

        $behavior->acquireTreeLock();

        $observed = $db
            ->createCommand('SELECT timeout FROM observed_waits')
            ->queryColumn();

        self::assertNotEmpty(
            $observed,
            'Guard update must be observed.',
        );

        foreach ($observed as $timeout) {
            self::assertContains(
                $timeout,
                [1000, '1000'],
                'Guard wait must be applied in milliseconds.',
            );
        }

        self::assertContains(
            $db->createCommand('PRAGMA busy_timeout')->queryScalar(),
            [23, '23'],
            'Busy timeout must be restored after success.',
        );

        $outer->rollBack();

        $db
            ->getMasterPdo()
            ->exec(
                "CREATE TRIGGER fail_guard AFTER UPDATE ON nested_sets_lock BEGIN SELECT RAISE(ABORT, 'injected guard failure'); END",
            );

        $outer = $db->beginTransaction();

        try {
            $behavior->acquireTreeLock();

            self::fail(
                'A guard SQL failure must propagate.',
            );
        } catch (Exception $exception) {
            self::assertStringContainsString(
                'injected guard failure',
                $exception->getMessage(),
                'Guard failure message must propagate.',
            );
            self::assertContains(
                $db->createCommand('PRAGMA busy_timeout')->queryScalar(),
                [23, '23'],
                'Busy timeout must be restored after failure.',
            );
            self::assertTrue($outer->getIsActive(), 'Outer transaction must stay active.');
        } finally {
            $outer->rollBack();
        }
    }

    public function testCatalogLookupSupportsUppercasePdoLabels(): void
    {
        // Yii's SQLite schema loader reads PRAGMA rows by lower-case keys, so metadata must be loaded before CASE_UPPER.
        Tree::getTableSchema();

        $this
            ->getDb()
            ->getTableSchema('`nested_sets_lock`');

        $pdo = $this
            ->getDb()
            ->getMasterPdo();

        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

        $outer = $this
            ->getDb()
            ->beginTransaction();

        try {
            $this
                ->behavior()
                ->acquireTreeLock();

            self::assertSame(
                2,
                (int) $this->getDb()->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
                'Table resource must be registered once.',
            );
        } finally {
            $outer->rollBack();

            $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
        }
    }

    public function testCatalogMetadataAcceptsTheMainSchema(): void
    {
        Tree::getTableSchema()->schemaName = 'main';

        $outer = $this
            ->getDb()
            ->beginTransaction();

        $this
            ->behavior()
            ->acquireTreeLock();

        self::assertSame(
            2,
            (int) $this->getDb()->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
            'Table resource must be registered once.',
        );

        $outer->rollBack();
    }

    public function testCurrentReadBypassesAnExplicitQueryCache(): void
    {
        Yii::$app->set('cache', new ArrayCache());

        $db = $this->getDb();

        $db->enableQueryCache = true;

        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $query = Tree::find()
            ->where(['id' => $root->getPrimaryKey()])
            ->cache(60);
        $cached = $query->one();

        self::assertInstanceOf(
            Tree::class,
            $cached,
            'Cached read must return the root.',
        );
        self::assertSame(
            2,
            $cached->getAttribute('rgt'),
            'Cached read must see the initial boundary.',
        );

        (new Tree(['name' => 'child']))->appendTo($root);

        $current = TreeLock::one($query);

        self::assertInstanceOf(
            Tree::class,
            $current,
            'Current read must return the root.',
        );
        self::assertSame(
            4,
            $current->getAttribute('rgt'),
            'Current read must see the updated boundary.',
        );
    }

    public function testExistenceUsesPortableSqlAndPreservesTheCallersQuery(): void
    {
        $query = Tree::find()
            ->orderBy(['lft' => SORT_ASC])
            ->cache(60);

        $originalSql = $query
            ->createCommand()
            ->getRawSql();

        $logger = Yii::getLogger();

        $logger->flush();

        self::assertFalse(
            TreeLock::exists($query),
            'Empty table must report no rows.',
        );
        self::assertSame(
            $originalSql,
            $query->createCommand()->getRawSql(),
            'Caller query SQL must be unchanged.',
        );
        self::assertSame(
            60,
            $query->queryCacheDuration,
            'Caller query cache duration must be unchanged.',
        );

        $found = false;

        foreach ($logger->messages as $message) {
            $sql = $this->loggedQuerySql($message);

            if ($sql !== null && str_starts_with($sql, 'SELECT EXISTS(')) {
                self::assertStringNotContainsString(
                    'ORDER BY',
                    $sql,
                    'Existence SQL must be portable to SQL Server subqueries.',
                );

                $found = true;
            }
        }

        self::assertTrue(
            $found,
            'The actual existence command must be observed.',
        );
    }

    public function testExistingResourceIdentitySurvivesDeployment(): void
    {
        $db = $this->getDb();

        // Frozen from the previously registered SQLite resource, not recomputed with the implementation under test.
        $db
            ->createCommand()
            ->insert(
                'nested_sets_lock',
                [
                    'resource' => 'acfc89b83ac6b489850dab8558fc139a9b7ad1cf437da2f9333033b863309bf0',
                    'token' => 0,
                ],
            )->execute();
        $db
            ->getMasterPdo()
            ->exec(
                "CREATE TRIGGER reject_registration BEFORE UPDATE ON nested_sets_lock WHEN OLD.resource = '_bootstrap' BEGIN SELECT RAISE(ABORT, 'Existing resources must not register again'); END",
            );

        $outer = $db->beginTransaction();

        $this
            ->behavior()
            ->acquireTreeLock();

        self::assertSame(
            2,
            (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
            'Existing resource must be reused.',
        );

        $outer->rollBack();
    }

    public function testExistsReadBypassesAnExplicitQueryCache(): void
    {
        Yii::$app->set('cache', new ArrayCache());

        $this
            ->getDb()
            ->enableQueryCache = true;

        $query = Tree::find()->cache(60);

        self::assertFalse(
            $query->exists(),
            'Cached query must report no rows.',
        );
        self::assertTrue(
            (new Tree(['name' => 'root']))->makeRoot(),
            'Root must be created.',
        );
        self::assertTrue(
            TreeLock::exists($query),
            'Current read must see the new root.',
        );
    }

    public function testFirstAcquisitionTogglesTheRegisteredTokenToOne(): void
    {
        $db = $this->getDb();

        $outer = $db->beginTransaction();

        $this
            ->behavior()
            ->acquireTreeLock();
        $outer->commit();

        self::assertContains(
            $db->createCommand("SELECT token FROM nested_sets_lock WHERE resource <> '_bootstrap'")->queryScalar(),
            [1, '1'],
            'Registered token must start at `0` and toggle once.',
        );
    }

    public function testOrdinaryUpdateSavesWithoutTransactionAndWithoutTheWriterLock(): void
    {
        $db = $this->getDb();

        $root = new NonTransactionalTree(['name' => 'root']);

        $root->makeRoot();

        $tokens = $db
            ->createCommand('SELECT resource, token FROM nested_sets_lock ORDER BY resource')
            ->queryAll();

        $root->setAttribute('name', 'renamed');

        self::assertTrue(
            $root->save(false),
            'Non-structural update must be saved.',
        );

        $stored = Tree::findOne($root->getPrimaryKey());

        self::assertInstanceOf(
            Tree::class,
            $stored,
            'Root must still be persisted.',
        );
        self::assertSame(
            'renamed',
            $stored->getAttribute('name'),
            'Stored name must change.',
        );
        self::assertSame(
            $tokens,
            $db->createCommand('SELECT resource, token FROM nested_sets_lock ORDER BY resource')->queryAll(),
            'Lock tokens must stay unchanged.',
        );
        self::assertNull(
            $db->getTransaction(),
            'No transaction may remain open.',
        );
    }

    public function testRegisteredTablesDoNotAcquireTheSharedBootstrapRow(): void
    {
        $db = $this->getDb();
        $outer = $db->beginTransaction();
        $behavior = $this->behavior();

        $behavior->acquireTreeLock();

        $outer->commit();

        $before = $db
            ->createCommand("SELECT token FROM nested_sets_lock WHERE resource = '_bootstrap'")
            ->queryScalar();
        $outer = $db->beginTransaction();

        $behavior->acquireTreeLock();

        self::assertSame(
            $before,
            $db->createCommand("SELECT token FROM nested_sets_lock WHERE resource = '_bootstrap'")->queryScalar(),
            'Bootstrap token must stay unchanged.',
        );

        $outer->rollBack();
    }

    public function testRegistrationRechecksTheResourceAfterAcquiringBootstrap(): void
    {
        $db = $this->getDb();
        $behavior = $this->behavior();
        $outer = $db->beginTransaction();

        $behavior->acquireTreeLock();

        $outer->commit();

        $db->createCommand(
            "CREATE TABLE registered_resource AS SELECT resource FROM nested_sets_lock WHERE resource <> '_bootstrap'",
        )->execute();
        $db->createCommand(
            "DELETE FROM nested_sets_lock WHERE resource <> '_bootstrap'",
        )->execute();

        // Inject the same visibility change as a competing registration completing before the bootstrap lock is acquired.
        $db
            ->getMasterPdo()
            ->exec(
                "CREATE TRIGGER register_resource AFTER UPDATE ON nested_sets_lock WHEN OLD.resource = '_bootstrap' BEGIN INSERT INTO nested_sets_lock (resource, token) SELECT resource, 0 FROM registered_resource; END",
            );

        $outer = $db->beginTransaction();

        $behavior->acquireTreeLock();

        self::assertSame(
            2,
            (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
            'Competing registration must not be duplicated.',
        );

        $outer->rollBack();
    }

    public function testServerRollbackClearsAllYiiLevelsAndAllowsAFreshOperation(): void
    {
        $db = $this->getDb();

        $db
            ->getMasterPdo()
            ->exec(
                "CREATE TRIGGER abort_insert BEFORE INSERT ON tree BEGIN SELECT RAISE(ROLLBACK, 'injected native rollback'); END",
            );
        $db->beginTransaction();

        $root = new Tree(['name' => 'root']);

        try {
            $root->makeRoot();

            self::fail(
                'The injected native rollback must fail the operation.',
            );
        } catch (Exception $exception) {
            self::assertMatchesRegularExpression(
                '/injected native rollback|no such savepoint: LEVEL[0-9]+/',
                $exception->getMessage(),
                'Failure must come from the native rollback.',
            );
            self::assertNull(
                $db->getTransaction(),
                'No Yii transaction level may remain.',
            );
            self::assertFalse(
                $db->getMasterPdo()->inTransaction(),
                'Native transaction must be closed.',
            );
            self::assertTrue(
                $root->getIsNewRecord(),
                'Owner must stay a new record.',
            );
        }

        $db
            ->createCommand('DROP TRIGGER abort_insert')
            ->execute();

        self::assertTrue(
            $root->makeRoot(),
            'Fresh operation must succeed.',
        );
    }

    public function testServerRollbackWithoutOuterTransactionClearsTheNativeFlagAndAllowsAFreshOperation(): void
    {
        $db = $this->getDb();

        $db
            ->getMasterPdo()
            ->exec(
                "CREATE TRIGGER abort_insert BEFORE INSERT ON tree BEGIN SELECT RAISE(ROLLBACK, 'injected native rollback'); END",
            );

        $root = new Tree(['name' => 'root']);

        try {
            $root->makeRoot();

            self::fail(
                'The injected native rollback must fail the operation.',
            );
        } catch (Exception $exception) {
            self::assertMatchesRegularExpression(
                '/injected native rollback|no such savepoint: LEVEL[0-9]+|cannot rollback - no transaction is active/',
                $exception->getMessage(),
                'Failure must come from the native rollback.',
            );
            self::assertNull(
                $db->getTransaction(),
                'No Yii transaction level may remain.',
            );
            self::assertFalse(
                $db->getMasterPdo()->inTransaction(),
                'Native transaction must be closed.',
            );
            self::assertTrue(
                $root->getIsNewRecord(),
                'Owner must stay a new record.',
            );
        }

        $db
            ->createCommand('DROP TRIGGER abort_insert')
            ->execute();

        self::assertTrue(
            $root->makeRoot(),
            'Fresh operation must succeed.',
        );
    }

    #[TestWith([1, 'injected rollback failure'])]
    #[TestWith([5, 'cannot rollback - no transaction is active'])]
    public function testThrowExceptionWhenRollbackListenerFailsWithAnUnrelatedError(int $code, string $message): void
    {
        $db = $this->getDb();
        $outer = $db->beginTransaction();

        // Only SQLITE_ERROR (code 1) reporting a discarded transaction may be swallowed; anything else must propagate.
        $injected = new Exception($message, ['HY000', $code, $message]);

        $db->on(
            Connection::EVENT_ROLLBACK_TRANSACTION,
            static function () use ($injected): never {
                throw $injected;
            },
        );

        try {
            TreeLock::rollBack($db, $outer, new RuntimeException('cause'));

            self::fail(
                'The listener failure must propagate.',
            );
        } catch (Throwable $caught) {
            self::assertSame(
                $injected,
                $caught,
                'Listener failure must propagate unchanged.',
            );
            self::assertNull(
                $db->getTransaction(),
                'No Yii transaction level may remain.',
            );
            self::assertFalse(
                $db->getMasterPdo()->inTransaction(),
                'Native transaction must be closed.',
            );
        } finally {
            $db->off(Connection::EVENT_ROLLBACK_TRANSACTION);
        }
    }

    public function testThrowInvalidConfigExceptionForLockTimeoutOutsideDocumentedRange(): void
    {
        foreach ([0, 3601] as $timeout) {
            $behavior = $this->behavior();

            $behavior->lockTimeout = $timeout;

            try {
                $behavior->acquireTreeLock();

                self::fail(
                    'An out-of-range timeout must be rejected.',
                );
            } catch (InvalidConfigException $exception) {
                self::assertSame(
                    Message::LOCK_TIMEOUT_OUT_OF_RANGE->getMessage(),
                    $exception->getMessage(),
                    'Message must state the accepted range.',
                );
            }
        }

        self::assertSame(
            1,
            (int) $this->getDb()->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
            'No resource may be registered.',
        );
    }

    public function testThrowInvalidConfigExceptionForViewStorage(): void
    {
        $db = $this->getDb();

        $db
            ->createCommand('CREATE VIEW tree_view AS SELECT * FROM tree')
            ->execute();

        ConcurrencyTree::$table = 'tree_view';

        $outer = $db->beginTransaction();

        try {
            $behavior = (new ConcurrencyTree())->getBehavior('nestedSetsBehavior');

            self::assertInstanceOf(
                NestedSetsBehavior::class,
                $behavior,
                'Behavior must be attached to the view model.',
            );

            $behavior->acquireTreeLock();

            self::fail(
                'A view must be rejected before registering a guard.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                Message::LOCK_REQUIRES_PHYSICAL_TABLE->getMessage(),
                $exception->getMessage(),
                'Views must be rejected as nested set storage.',
            );
            self::assertSame(
                1,
                (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
                'No resource may be registered for a view.',
            );
        } finally {
            $outer->rollBack();

            ConcurrencyTree::$table = '{{%tree}}';

            $db
                ->createCommand('DROP VIEW tree_view')
                ->execute();
        }
    }

    public function testThrowInvalidConfigExceptionWhenTransactionWasRolledBackNatively(): void
    {
        $db = $this->getDb();
        $outer = $db->beginTransaction();

        $db->getMasterPdo()->rollBack();

        try {
            $this->behavior()->acquireTreeLock();

            self::fail(
                'A transaction ended outside Yii must be rejected.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                Message::TRANSACTION_REQUIRED->getMessage(),
                $exception->getMessage(),
                'Message must name the missing Yii transaction.',
            );
            self::assertFalse(
                $db->getMasterPdo()->inTransaction(),
                'No native transaction may be started.',
            );
        } finally {
            $outer->rollBack();
        }
    }

    public function testThrowNotSupportedExceptionForAttachedStorageSchema(): void
    {
        $db = $this->getDb();

        // TableSchema is an extension point; a custom schema must not redirect the guard to main silently.
        Tree::getTableSchema()->schemaName = 'attached';

        $outer = $db->beginTransaction();

        try {
            $this
                ->behavior()
                ->acquireTreeLock();

            self::fail(
                'Attached storage metadata must be rejected before registering a main-database guard.',
            );
        } catch (NotSupportedException $exception) {
            self::assertSame(
                Message::LOCK_REQUIRES_SQLITE_MAIN_DATABASE->getMessage(),
                $exception->getMessage(),
                'Message must state the main database restriction.',
            );
            self::assertSame(
                1,
                (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
                'No resource may be registered.',
            );
            self::assertTrue(
                $outer->getIsActive(),
                'Outer transaction must stay active.',
            );
        } finally {
            $outer->rollBack();
        }
    }

    public function testThrowNotSupportedExceptionForUnsupportedDriver(): void
    {
        $db = $this->getDb();

        Tree::getTableSchema();

        $outer = $db->beginTransaction();

        $db->setDriverName('unsupported');

        try {
            $this->behavior()->acquireTreeLock();

            self::fail(
                'An unsupported driver must fail before any guard write.',
            );
        } catch (NotSupportedException $exception) {
            self::assertSame(
                Message::LOCK_DRIVER_NOT_SUPPORTED->getMessage(),
                $exception->getMessage(),
                'Message must name the unsupported driver.',
            );
            self::assertSame(
                1,
                (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
                'No resource may be registered.',
            );
        } finally {
            $db->setDriverName('sqlite');
            $outer->rollBack();
        }
    }

    public function testThrowNotSupportedExceptionWhenSavepointsAreDisabled(): void
    {
        $db = $this->getDb();
        $outer = $db->beginTransaction();

        $db->enableSavepoint = false;

        try {
            $this->behavior()->acquireTreeLock();

            self::fail(
                'Disabled savepoints must be rejected before the guard savepoint.',
            );
        } catch (NotSupportedException $exception) {
            self::assertSame(
                Message::LOCK_REQUIRES_SAVEPOINTS->getMessage(),
                $exception->getMessage(),
                'Message must name the savepoint requirement.',
            );
            self::assertSame(
                1,
                (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
                'No resource may be registered.',
            );
        } finally {
            $db->enableSavepoint = true;
            $outer->rollBack();
        }
    }

    #[TestWith([1])]
    #[TestWith([3600])]
    public function testTimeoutAcceptsBothDocumentedLimits(int $timeout): void
    {
        $outer = $this
            ->getDb()
            ->beginTransaction();
        $behavior = $this->behavior();

        $behavior->lockTimeout = $timeout;

        $behavior->acquireTreeLock();

        self::assertSame(
            2,
            (int) $this->getDb()->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
            'Table resource must be registered once.',
        );

        $outer->rollBack();
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

    private function loggedQuerySql(mixed $message): string|null
    {
        if (is_array($message) === false) {
            throw new RuntimeException('Unexpected Yii log entry format.');
        }

        if (($message[1] ?? null) !== Logger::LEVEL_INFO || ($message[2] ?? null) !== 'yii\db\Command::query') {
            return null;
        }

        $sql = $message[0] ?? null;

        self::assertIsString(
            $sql,
            'Logged query must be a string.',
        );

        return $sql;
    }
}
