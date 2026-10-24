<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PHPUnit\Framework\Attributes\Group;
use yii\db\StaleObjectException;
use yii2\extensions\nestedsets\exception\Message;
use yii2\extensions\nestedsets\tests\base\AbstractIntegrity;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;
use yii2\extensions\nestedsets\tests\support\model\Tree;

/**
 * Runs the {@see AbstractIntegrity} scenarios against SQLite.
 */
#[Group('sqlite')]
final class IntegrityTest extends AbstractIntegrity
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::SQLITE->connection();

        parent::setUp();
    }

    public function testThrowStaleObjectExceptionWhenInsertedNodeIsDeletedBeforeReload(): void
    {
        $db = $this->getDb();

        $db
            ->getMasterPdo()
            ->exec(
                <<<SQL
                CREATE TRIGGER drop_inserted AFTER INSERT ON tree
                BEGIN
                    DELETE FROM tree WHERE id = NEW.id;
                END
                SQL,
            );

        $root = new Tree(['name' => 'root']);

        try {
            $root->makeRoot();

            self::fail(
                'An inserted node that cannot be reloaded must be rejected.',
            );
        } catch (StaleObjectException $exception) {
            self::assertSame(
                Message::NODE_NOT_FOUND->getMessage(),
                $exception->getMessage(),
                'Message must report the missing node.',
            );
            self::assertTrue(
                $root->getIsNewRecord(),
                'Owner must stay a new record.',
            );
            self::assertSame(
                0,
                (int) $db->createCommand('SELECT COUNT(*) FROM tree')->queryScalar(),
                'Table must stay empty.',
            );
            self::assertNull(
                $db->getTransaction(),
                'No transaction may remain open.',
            );
        } finally {
            $db
                ->createCommand('DROP TRIGGER drop_inserted')
                ->execute();
        }
    }
}
