<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PDO;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii2\extensions\nestedsets\exception\Message;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\{DatabaseConnection, ReportedCaseModeConnection};
use yii2\extensions\nestedsets\tests\support\model\{ConcurrencyTree, Tree};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Unit tests for the {@see NestedSetsBehavior} writer lock identifier case and storage engine checks against MySQL.
 */
#[Group('mysql')]
#[Group('mutation')]
final class WriterLockTest extends TestCase
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MYSQL->connection();
        $this->connection['class'] = ReportedCaseModeConnection::class;

        parent::setUp();

        $this->createDatabase();
    }

    #[TestWith([0, false])]
    #[TestWith([0, true])]
    #[TestWith([1, false])]
    #[TestWith([1, true])]
    #[TestWith([2, false])]
    #[TestWith([2, true])]
    public function testAcceptsEveryReportedIdentifierMode(int $mode, bool $stringify): void
    {
        $db = $this->getDb();

        self::assertInstanceOf(
            ReportedCaseModeConnection::class,
            $db,
            'Fixture connection must report case modes.',
        );

        $db->reportedCaseMode = $mode;

        $db
            ->getMasterPdo()
            ->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);

        $outer = $db->beginTransaction();

        $this
            ->behavior()
            ->acquireTreeLock();

        self::assertSame(
            2,
            (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
            'Table resource must be registered once.',
        );

        $outer->rollBack();
    }

    public function testAppendUsesCurrentCoordinatesAfterAnOlderSnapshot(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $db = $this->getDb();

        $other = new Connection(['dsn' => $db->dsn, 'username' => $db->username, 'password' => $db->password]);

        $outer = $db->beginTransaction('REPEATABLE READ');

        try {
            $snapshotRoot = Tree::findOne($root->getPrimaryKey());

            self::assertInstanceOf(
                Tree::class,
                $snapshotRoot,
                'Snapshot root must be loaded.',
            );
            self::assertSame(
                2,
                $snapshotRoot->getAttribute('rgt'),
                'Snapshot must see the initial boundary.',
            );

            Yii::$app->set('db', $other);

            try {
                $currentRoot = Tree::findOne($root->getPrimaryKey());

                self::assertInstanceOf(
                    Tree::class,
                    $currentRoot,
                    'Current root must be loaded.',
                );
                self::assertTrue(
                    (new Tree(['name' => 'concurrent']))->appendTo($currentRoot),
                    'Concurrent append must succeed.',
                );
            } finally {
                Yii::$app->set('db', $db);
            }

            self::assertTrue(
                $snapshotRoot->refresh(),
                'Refresh must succeed.',
            );
            self::assertSame(
                2,
                $snapshotRoot->getAttribute('rgt'),
                'The first session must still have its older snapshot.',
            );
            self::assertTrue(
                (new Tree(['name' => 'appended']))->appendTo($root),
                'Append must succeed.',
            );

            $outer->commit();

            self::assertSame(
                ['concurrent', 'appended'],
                $root->children(1)->select('name')->column(),
                'Append must follow the concurrent child.',
            );
        } finally {
            Yii::$app->set('db', $db);

            $outer->rollBack();
            $other->close();
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testCaseDistinctPhysicalTablesNeverShareAGuard(bool $stringify): void
    {
        $db = $this->getDb();

        $nativeMode = $db
            ->getMasterPdo()
            ->query('SELECT @@lower_case_table_names');

        self::assertNotFalse(
            $nativeMode,
            'Server case mode must be readable.',
        );

        if (in_array($nativeMode->fetchColumn(), [0, '0'], true) === false) {
            self::markTestSkipped(
                'Case-distinct table names require lower_case_table_names=0.',
            );
        }

        $db
            ->getMasterPdo()
            ->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);

        $outer = $db->beginTransaction();

        $this
            ->behavior()
            ->acquireTreeLock();

        $lowerKey = $db
            ->createCommand("SELECT resource FROM nested_sets_lock WHERE resource <> '_bootstrap'")
            ->queryScalar();

        self::assertIsString(
            $lowerKey,
            'Lower-case table resource must be registered.',
        );

        $outer->commit();

        $db
            ->createCommand('CREATE TABLE `TREE` LIKE `tree`')
            ->execute();

        ConcurrencyTree::$table = 'TREE';

        try {
            $outer = $db->beginTransaction();

            $upperBehavior = (new ConcurrencyTree())->getBehavior('nestedSetsBehavior');

            self::assertInstanceOf(
                NestedSetsBehavior::class,
                $upperBehavior,
                'Model must expose the nested sets behavior.',
            );

            $upperBehavior->acquireTreeLock();

            $outer->commit();

            self::assertSame(
                3,
                (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
                'Upper-case table must register its own resource.',
            );

            $before = $db
                ->createCommand('SELECT token FROM nested_sets_lock WHERE resource = :key', [':key' => $lowerKey])
                ->queryScalar();
            $outer = $db->beginTransaction();

            $this
                ->behavior()
                ->acquireTreeLock();

            $after = $db
                ->createCommand('SELECT token FROM nested_sets_lock WHERE resource = :key', [':key' => $lowerKey])
                ->queryScalar();

            self::assertNotSame(
                $before,
                $after,
                'The lower-case table must keep acquiring its own guard.',
            );

            $outer->rollBack();
        } finally {
            $db->getTransaction()?->rollBack();
            $db->createCommand('DROP TABLE `TREE`')->execute();

            ConcurrencyTree::$table = '{{%tree}}';
        }
    }

    #[TestWith(['tree'])]
    #[TestWith(['nested_sets_lock'])]
    public function testThrowInvalidConfigExceptionForNontransactionalStorage(string $table): void
    {
        $db = $this->getDb();

        $quoted = $db->quoteTableName($table);

        $db
            ->createCommand('ALTER TABLE ' . $quoted . ' ENGINE=MyISAM')
            ->execute();

        try {
            (new Tree(['name' => 'root']))->makeRoot();

            self::fail(
                'A nontransactional model or registry must be rejected before writing.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                Message::LOCK_REQUIRES_INNODB->getMessage(),
                $exception->getMessage(),
                'Message must name the required engine.',
            );
            self::assertSame(
                0,
                (int) Tree::find()->count(),
                'No root may be written.',
            );
            self::assertNull(
                $db->getTransaction(),
                'No transaction may remain open.',
            );
        } finally {
            $db->getTransaction()?->rollBack();
            $db
                ->createCommand("ALTER TABLE {$quoted} ENGINE=InnoDB")
                ->execute();
        }
    }

    public function testThrowInvalidConfigExceptionForRegistryMissingFromOwnerSchema(): void
    {
        $db = $this->getDb();

        $token = getenv('TEST_TOKEN');

        $token = $token === false || $token === '' ? 'initial' : $token;

        $probe = 'nested_sets_probe_' . preg_replace('/\W/', '_', $token);

        $fixture = $db
            ->createCommand('SELECT DATABASE()')
            ->queryScalar();

        self::assertIsString(
            $fixture,
            'Fixture database must be selected.',
        );

        // MySQL DDL commits implicitly, so the probe schema is prepared before any transaction starts.
        $db
            ->createCommand('DROP DATABASE IF EXISTS ' . $db->quoteTableName($probe))
            ->execute();
        $db
            ->createCommand('CREATE DATABASE ' . $db->quoteTableName($probe))
            ->execute();
        $db
            ->createCommand(
                'CREATE TABLE ' . $db->quoteTableName($probe . '.tree')
                . ' LIKE ' . $db->quoteTableName($fixture . '.tree'),
            )
            ->execute();

        ConcurrencyTree::$table = $probe . '.tree';

        try {
            $db->beginTransaction();

            $behavior = (new ConcurrencyTree())->getBehavior('nestedSetsBehavior');

            self::assertInstanceOf(
                NestedSetsBehavior::class,
                $behavior,
                'Behavior must be attached to the probe model.',
            );

            $behavior->acquireTreeLock();

            self::fail(
                'A registry missing from the owner schema must be rejected before writing.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                Message::LOCK_REQUIRES_INNODB->getMessage(),
                $exception->getMessage(),
                'Message must name the required engine.',
            );
            self::assertSame(
                1,
                (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
                'No resource may be registered.',
            );
        } finally {
            $db->getTransaction()?->rollBack();

            ConcurrencyTree::$table = '{{%tree}}';

            $db
                ->createCommand('DROP DATABASE IF EXISTS ' . $db->quoteTableName($probe))
                ->execute();
        }
    }

    public function testThrowInvalidConfigExceptionForUnknownReportedIdentifierMode(): void
    {
        $db = $this->getDb();

        self::assertInstanceOf(
            ReportedCaseModeConnection::class,
            $db,
            'Fixture connection must report case modes.',
        );

        $db->reportedCaseMode = 3;

        $outer = $db->beginTransaction();

        try {
            $this
                ->behavior()
                ->acquireTreeLock();

            self::fail(
                'Unknown identifier modes must fail before registering a guard.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                Message::CANNOT_RESOLVE_MYSQL_IDENTIFIER_CASE->getMessage(),
                $exception->getMessage(),
                'Message must name the identifier case rules.',
            );
            self::assertSame(
                1,
                (int) $db->createCommand('SELECT COUNT(*) FROM nested_sets_lock')->queryScalar(),
                'No resource may be registered.',
            );
        } finally {
            $outer->rollBack();
        }
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
}
