<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\pgsql;

use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii2\extensions\nestedsets\exception\Message;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;
use yii2\extensions\nestedsets\tests\support\model\{ConcurrencyTree, Tree};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Unit tests for the {@see NestedSetsBehavior} writer lock timeout applied and restored on the PostgreSQL server.
 */
#[Group('pgsql')]
#[Group('mutation')]
final class WriterLockTest extends TestCase
{
    public function testGuardTimeoutUsesSecondsAndRestoresThePriorSetting(): void
    {
        $db = $this->getDb();

        $db
            ->createCommand('CREATE TEMP TABLE observed_guard_waits (timeout_text text)')
            ->execute();
        $db
            ->getMasterPdo()
            ->exec(
                "CREATE FUNCTION pg_temp.capture_guard_wait() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN INSERT INTO pg_temp.observed_guard_waits VALUES (current_setting(''lock_timeout'')); RETURN NEW; END'",
            );
        $db
            ->createCommand(
                'CREATE TRIGGER capture_guard_wait BEFORE UPDATE ON nested_sets_lock FOR EACH ROW EXECUTE FUNCTION pg_temp.capture_guard_wait()',
            )
            ->execute();

        $previous = $db
            ->createCommand("SELECT current_setting('lock_timeout')")
            ->queryScalar();

        $behavior = (new Tree())->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            NestedSetsBehavior::class,
            $behavior,
            'Model must expose the nested sets behavior.',
        );

        $behavior->lockTimeout = 2;

        $outer = $db->beginTransaction();

        try {
            $behavior->acquireTreeLock();

            $observed = $db
                ->createCommand('SELECT timeout_text FROM pg_temp.observed_guard_waits')
                ->queryColumn();

            self::assertNotEmpty(
                $observed,
                'Guard update must be observed.',
            );

            foreach ($observed as $timeout) {
                self::assertSame(
                    '2s',
                    $timeout,
                    'Guard wait must be applied in seconds.',
                );
            }

            self::assertSame(
                $previous,
                $db->createCommand("SELECT current_setting('lock_timeout')")->queryScalar(),
                'Lock wait setting must be restored.',
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

    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::PGSQL->connection();

        parent::setUp();

        $this->createDatabase();
    }
}
