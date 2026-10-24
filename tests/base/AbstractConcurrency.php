<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii2\extensions\nestedsets\exception\Message;
use yii2\extensions\nestedsets\{NestedSetsBehavior, TreeLock};
use yii2\extensions\nestedsets\tests\support\ConcurrentProcess;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, NonTransactionalTree, Tree};
use yii2\extensions\nestedsets\tests\TestCase;

use function in_array;

/**
 * Unit tests for the {@see NestedSetsBehavior} writer lock across independent PHP processes and database sessions.
 */
abstract class AbstractConcurrency extends TestCase
{
    private string $directory = '';

    /**
     * @var list<ConcurrentProcess>
     */
    private array $workers = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/nestedsets-' . bin2hex(random_bytes(8));

        mkdir($this->directory);

        if (str_starts_with($this->connection['dsn'] ?? '', 'sqlite:')) {
            $this->connection['dsn'] = "sqlite:{$this->directory}/test.sqlite";
        }

        parent::setUp();

        $this->createDatabase();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->close();
        }

        $this
            ->getDb()
            ->close();

        foreach (($files = glob($this->directory . '/*')) === false ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->directory);

        parent::tearDown();
    }

    public function testConcurrentCrossTreeMoveReloadsSourceAndTarget(): void
    {
        $firstRoot = new MultipleTree(['name' => 'first-root']);

        $firstRoot->makeRoot();

        $secondRoot = new MultipleTree(['name' => 'second-root']);

        $secondRoot->makeRoot();

        $source = new MultipleTree(['name' => 'source']);

        $source->appendTo($firstRoot);

        $leaf = new MultipleTree(['name' => 'leaf']);

        $leaf->appendTo($source);

        $other = new MultipleTree(['name' => 'other']);

        $other->appendTo($firstRoot);

        $gate = "{$this->directory}/release";
        $secondGate = "{$this->directory}/start-second";
        $payload = [
            'model' => 'multiple',
            'operation' => 'append',
            'source' => $source->getAttributes(),
            'target' => $secondRoot->getAttributes(),
        ];

        $second = $this->start(
            $payload + ['waitBefore' => true,
                'gate' => $secondGate],
        );

        $second->await('prepared');

        $first = $this->start(
            [
                'model' => 'multiple',
                'operation' => 'append',
                'source' => $other->getAttributes(),
                'target' => $secondRoot->getAttributes(),
                'outer' => true,
                'hold' => 'after-method',
                'gate' => $gate,
            ],
        );

        $first->await('held');

        touch($secondGate);

        $blocked = $second->await('finished');

        touch($gate);

        $this->assertWorkerSucceeded(
            $first->await('finished'),
            'Holding writer must commit.',
        );

        $this->assertLockFailure($blocked);

        $this->assertWorkerSucceeded(
            $this->start($payload)->await('finished'),
            'Retried move must commit.',
        );

        $firstRoot->refresh();
        $secondRoot->refresh();
        $source->refresh();
        $leaf->refresh();

        self::assertSame(
            2,
            $firstRoot->getAttribute('rgt'),
            'Source root must close over itself only.',
        );
        self::assertSame(
            8,
            $secondRoot->getAttribute('rgt'),
            'Target root must enclose both moved subtrees.',
        );
        self::assertSame(
            ['other', 'source'],
            $secondRoot->children(1)->select('name')->column(),
            'Moved nodes must keep their commit order.',
        );
        self::assertSame(
            $secondRoot->getAttribute('tree'),
            $source->getAttribute('tree'),
            'Source must belong to the target tree.',
        );
        self::assertSame(
            $secondRoot->getAttribute('tree'),
            $leaf->getAttribute('tree'),
            'Descendant must follow the source tree.',
        );
        self::assertSame(
            ['second-root', 'source'],
            $leaf->parents()->select('name')->column(),
            'Descendant ancestry must point to the target root.',
        );
    }

    public function testConcurrentOrdinaryDeleteAndSubtreeDeleteUseTheWriterLock(): void
    {
        foreach (['delete', 'subtree'] as $operation) {
            $this->createDatabase();

            $root = new Tree(['name' => 'root']);

            $root->makeRoot();

            $branch = new Tree(['name' => 'branch']);

            $branch->appendTo($root);

            $leaf = new Tree(['name' => 'leaf']);

            $leaf->appendTo($branch);

            $gate = "{$this->directory}/release-$operation";
            $secondGate = "{$this->directory}/start-$operation";
            $payload = [
                'operation' => $operation,
                'source' => $branch->getAttributes(),
            ];

            $second = $this->start(
                $payload + ['waitBefore' => true, 'gate' => $secondGate],
            );

            $second->await('prepared');

            $first = $this->start(
                [
                    'operation' => 'prepend',
                    'target' => $root->getAttributes(),
                    'name' => 'inserted',
                    'outer' => true,
                    'hold' => 'after-method',
                    'gate' => $gate,
                ],
            );

            $first->await('held');

            touch($secondGate);

            $blocked = $second->await('finished');

            touch($gate);

            $this->assertWorkerSucceeded(
                $first->await('finished'),
                'Holding writer must commit.',
            );

            $this->assertLockFailure($blocked);

            $this->assertWorkerSucceeded(
                $this->start($payload)->await('finished'),
                'Retried deletion must commit.',
            );

            $root->refresh();

            $expected = $operation === 'delete' ? ['root', 'inserted', 'leaf'] : ['root', 'inserted'];

            self::assertSame(
                $expected,
                Tree::find()->orderBy('lft')->select('name')->column(),
                'Remaining nodes must match the deletion mode.',
            );
            self::assertSame(
                count($expected) * 2,
                $root->getAttribute('rgt'),
                'Root must close over remaining nodes.',
            );
        }
    }

    public function testConcurrentSingleRootCreationIsSerialized(): void
    {
        $gate = "{$this->directory}/release";
        $secondGate = "{$this->directory}/start-second";

        $second = $this->start(
            [
                'waitBefore' => true,
                'gate' => $secondGate,
                'name' => 'second',
            ],
        );

        $second->await('prepared');

        $first = $this->start(
            [
                'outer' => true,
                'hold' => 'before-insert',
                'gate' => $gate,
                'name' => 'first',
            ],
        );

        $first->await('held');

        touch($secondGate);

        $secondResult = $second->await('finished');

        touch($gate);

        $this->assertWorkerSucceeded(
            $first->await('finished'),
            'Holding writer must commit.',
        );
        self::assertSame(
            1,
            (int) Tree::find()->count(),
            'Only one root may commit.',
        );

        $this->assertLockFailure($secondResult);
    }

    public function testDeadlockDoesNotLeaveAnUnnoticedAbortedTransaction(): void
    {
        if ($this->getDb()->driverName === 'sqlite') {
            self::markTestSkipped(
                'SQLite has only one file writer and cannot form this row-lock cycle.',
            );
        }

        (new Tree(['name' => 'root']))->makeRoot();
        (new MultipleTree(['name' => 'other']))->makeRoot();

        $gate = $this->directory . '/deadlock';

        $first = $this->start(
            [
                'operation' => 'lock-two',
                'outer' => true,
                'lockSeconds' => 10,
                'gate' => $gate,
            ],
        );

        $first->await('held');

        $second = $this->start(
            [
                'operation' => 'lock-two',
                'model' => 'multiple',
                'outer' => true,
                'lockSeconds' => 10,
                'gate' => $gate,
            ],
        );

        $second->await('held');

        touch($gate);

        $results = [$first->await('finished', 20), $second->await('finished', 20)];
        $failures = array_values(
            array_filter(
                $results,
                static fn(array $result): bool => ($result['success'] ?? false) === false,
            ),
        );

        self::assertCount(
            1,
            $failures,
            'Exactly one writer must fail. Results: ' . json_encode($results, JSON_THROW_ON_ERROR),
        );

        $failure = $failures[0] ?? null;

        self::assertIsArray(
            $failure,
            'Failure report must be an array.',
        );

        $exception = $failure['exception'] ?? null;

        self::assertIsString(
            $exception,
            'Failure must report its exception class.',
        );
        self::assertTrue(
            is_a($exception, Exception::class, true),
            'Failure must be a database exception.',
        );

        $message = $failure['message'] ?? null;

        self::assertIsString(
            $message,
            'Failure must report its message.',
        );
        self::assertMatchesRegularExpression(
            '/deadlock/i',
            $message,
            'Failure must be reported as a deadlock.',
        );

        if (in_array($this->getDb()->driverName, ['mysql', 'sqlsrv'], true)) {
            self::assertFalse(
                $failure['transactionActive'] ?? true,
                'Yii must not report an active transaction after the server rolled it back.',
            );
            self::assertFalse(
                $failure['nativeActive'] ?? true,
                'Native transaction must be closed.',
            );
        }

        self::assertSame(
            $failure['timeoutBefore'] ?? null,
            $failure['timeoutAfter'] ?? null,
            'Lock wait setting must be restored.',
        );
    }

    public function testExplicitLockIsReentrantAndCoversApplicationWork(): void
    {
        $gate = $this->directory . '/start-second';

        $second = $this->start(
            [
                'waitBefore' => true,
                'gate' => $gate,
            ],
        );

        $second->await('prepared');

        $outer = $this
            ->getDb()
            ->beginTransaction();

        $model = new Tree();

        $behavior = $model->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            NestedSetsBehavior::class,
            $behavior,
            'Model must expose the nested sets behavior.',
        );

        $behavior->lockTimeout = 1;

        $behavior->acquireTreeLock();
        $behavior->acquireTreeLock();

        touch($gate);

        $this->assertLockFailure($second->await('finished'));

        $outer->rollBack();

        self::assertTrue(
            (new Tree(['name' => 'after-rollback']))->makeRoot(),
            'Rollback must release the lock for new writers.',
        );
    }

    public function testIndependentTablesCanWriteConcurrently(): void
    {
        if ($this->getDb()->driverName === 'sqlite') {
            self::markTestSkipped(
                'SQLite serializes writers for the entire database file.',
            );
        }

        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        (new MultipleTree(['name' => 'other']))->makeRoot();

        $secondGate = "{$this->directory}/start-second";

        $second = $this->start(
            [
                'model' => 'multiple',
                'name' => 'independent',
                'waitBefore' => true,
                'gate' => $secondGate,
            ],
        );

        $second->await('prepared');

        $gate = "{$this->directory}/release";

        $first = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'outer' => true,
                'hold' => 'after-method',
                'gate' => $gate,
            ],
        );

        $first->await('held');

        touch($secondGate);

        $independent = $second->await('finished');

        touch($gate);

        $this->assertWorkerSucceeded(
            $first->await('finished'),
            'Holding writer must commit.',
        );
        $this->assertWorkerSucceeded(
            $independent,
            'An existing guard for another table must not contend.',
        );
    }

    public function testOlderOuterSnapshotCannotCommitASecondRoot(): void
    {
        $driver = $this->getDb()->driverName;

        if ($driver === 'sqlite') {
            $this
                ->getDb()
                ->createCommand('PRAGMA journal_mode = WAL')
                ->queryScalar();
        }

        if ($driver === 'sqlsrv') {
            // Only this disposable fixture database is configured; no server or security settings change.
            $this
                ->getDb()
                ->createCommand('ALTER DATABASE CURRENT SET ALLOW_SNAPSHOT_ISOLATION ON')
                ->execute();
        }

        $isolation = match ($driver) {
            'pgsql', 'mysql' => 'REPEATABLE READ',
            'oci' => 'SERIALIZABLE',
            'sqlsrv' => 'SNAPSHOT',
            default => '',
        };
        $gate = "{$this->directory}/continue";

        $old = $this->start(
            [
                'outer' => true,
                'isolation' => $isolation,
                'warmSnapshot' => true,
                'waitBefore' => true,
                'gate' => $gate,
                'name' => 'old',
            ],
        );

        $old->await('prepared');

        $this->assertWorkerSucceeded(
            $this->start(['name' => 'current'])->await('finished'),
            'Current writer must commit.',
        );

        touch($gate);

        $result = $old->await('finished');

        self::assertFalse(
            $result['success'] ?? false,
            'Older snapshot must not commit.',
        );
        self::assertSame(
            ['current'],
            Tree::find()->select('name')->column(),
            'Only the current root may persist.',
        );

        if ($driver === 'mysql') {
            $message = $result['message'] ?? null;

            self::assertIsString(
                $message,
                'Failure must report its message.',
            );
            self::assertSame(
                Message::CANNOT_CREATE_MULTIPLE_ROOTS->getMessage(),
                $message,
                'Failure must name the single-root violation.',
            );
        }
    }

    public function testOrderedExistenceQueriesRemainReusable(): void
    {
        $query = Tree::find()->orderBy(['lft' => SORT_ASC]);

        $outer = $this
            ->getDb()
            ->beginTransaction();

        try {
            self::assertFalse(
                TreeLock::exists($query),
                'Empty table must report no rows.',
            );
            self::assertTrue(
                (new Tree(['name' => 'root']))->makeRoot(),
                'Root must be created.',
            );
            self::assertTrue(
                TreeLock::exists($query),
                'Same query must see the new root.',
            );
        } finally {
            $outer->rollBack();
        }
    }

    public function testOuterRollbackReleasesLockAndAllChanges(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $gate = "{$this->directory}/release";
        $secondGate = "{$this->directory}/start-second";

        $second = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'name' => 'blocked',
                'waitBefore' => true,
                'gate' => $secondGate,
            ],
        );

        $second->await('prepared');

        $first = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'outer' => true,
                'hold' => 'after-method',
                'gate' => $gate,
                'name' => 'rolled-back',
                'rollback' => true,
            ],
        );

        $first->await('held');

        touch($secondGate);

        $blocked = $second->await('finished');

        touch($gate);

        $this->assertWorkerSucceeded(
            $first->await('finished'),
            'Holding writer must finish.',
        );

        $this->assertLockFailure($blocked);

        self::assertSame(
            ['root'],
            Tree::find()->select('name')->column(),
            'Rolled back changes must not persist.',
        );
        $this->assertWorkerSucceeded(
            $this->start(
                [
                    'operation' => 'append',
                    'target' => $root->getAttributes(),
                    'name' => 'retry',
                ],
            )->await('finished'),
            'Retried append must commit.',
        );

        $root->refresh();

        self::assertSame(
            4,
            $root->getAttribute('rgt'),
            'Root must enclose only the retried child.',
        );
    }

    public function testOuterTransactionKeepsWriterLockAfterMethodReturns(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $gate = "{$this->directory}/release";
        $secondGate = "{$this->directory}/start-second";

        $second = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'name' => 'second',
                'waitBefore' => true,
                'gate' => $secondGate,
            ],
        );

        $second->await('prepared');

        $first = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'outer' => true,
                'hold' => 'after-method',
                'gate' => $gate,
                'name' => 'first',
            ],
        );

        $first->await('held');

        touch($secondGate);

        $secondResult = $second->await('finished');

        touch($gate);

        $this->assertWorkerSucceeded(
            $first->await('finished'),
            'Holding writer must commit.',
        );

        $this->assertLockFailure($secondResult);

        $this->assertWorkerSucceeded(
            $this->start(
                [
                    'operation' => 'append',
                    'target' => $root->getAttributes(),
                    'name' => 'retry',
                ],
            )->await('finished'),
            'Retried append must commit.',
        );

        $root->refresh();

        self::assertSame(
            6,
            $root->getAttribute('rgt'),
            'Root must enclose both committed children.',
        );
        self::assertSame(
            ['first', 'retry'],
            $root->children()->select('name')->column(),
            'Children must keep their commit order.',
        );
    }

    public function testQualifiedAndPrefixedNamesShareThePhysicalTableLock(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $db = $this->getDb();

        $schema = match ($db->driverName) {
            'pgsql' => $db->createCommand('SELECT current_schema()')->queryScalar(),
            'mysql' => $db->createCommand('SELECT DATABASE()')->queryScalar(),
            'sqlsrv' => 'dbo',
            'oci' => $db->createCommand("SELECT SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') FROM DUAL")->queryScalar(),
            default => '',
        };

        self::assertIsString(
            $schema,
            'Current schema must be resolved.',
        );

        $table = $schema === '' ? 'TREE' : "{$schema}.tree";

        $gate = "{$this->directory}/release";
        $secondGate = "{$this->directory}/start-second";

        $qualified = $this->start(
            [
                'table' => $table,
                'waitBefore' => true,
                'gate' => $secondGate,
            ],
        );

        $qualified->await('prepared');

        $prefixed = $this->start(
            [
                'table' => '{{%ree}}',
                'prefix' => 't',
                'waitBefore' => true,
                'gate' => $secondGate,
            ],
        );

        $prefixed->await('prepared');

        $first = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'outer' => true,
                'hold' => 'after-method',
                'gate' => $gate,
            ],
        );

        $first->await('held');

        touch($secondGate);

        $this->assertLockFailure($qualified->await('finished'));
        $this->assertLockFailure($prefixed->await('finished'));

        touch($gate);

        $this->assertWorkerSucceeded(
            $first->await('finished'),
            'Holding writer must commit.',
        );
        self::assertSame(
            2,
            (int) $db->createCommand(
                <<<SQL
                SELECT COUNT(*) FROM [[nested_sets_lock]]
                SQL,
            )->queryScalar(),
            'Aliases must not register additional resources.',
        );
    }

    public function testReentrantWritesAndFailuresReleaseTheirLocks(): void
    {
        $result = $this->start(
            [
                'name' => 'root',
                'reenter' => true,
            ],
        )->await('finished');

        $this->assertWorkerSucceeded(
            $result,
            'Reentrant write must commit.',
        );
        self::assertSame(
            ['root', 'nested'],
            Tree::find()->orderBy('lft')->select('name')->column(),
            'Nested write must persist under the root.',
        );

        $root = Tree::find()
            ->roots()
            ->one();

        self::assertInstanceOf(
            Tree::class,
            $root,
            'Root must be persisted.',
        );

        $failure = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'name' => 'failed',
                'failAfter' => true,
            ],
        )->await('finished');

        self::assertFalse(
            $failure['success'] ?? false,
            'Injected failure must abort the write.',
        );
        self::assertSame(
            'Injected worker failure.',
            $failure['message'] ?? null,
            'Failure message must propagate.',
        );
        $this->assertWorkerSucceeded(
            $this->start(
                [
                    'operation' => 'append',
                    'target' => $root->getAttributes(),
                    'name' => 'after-failure',
                ],
            )->await('finished'),
            'Write after failure must commit.',
        );

        $root->refresh();

        self::assertSame(
            6,
            $root->getAttribute('rgt'),
            'Failed write must leave no gap.',
        );
    }

    public function testRegistrationIsRolledBackAndReacquiredAfterSavepointRollback(): void
    {
        $outer = $this
            ->getDb()
            ->beginTransaction();
        $savepoint = $this
            ->getDb()
            ->beginTransaction();

        self::assertTrue(
            (new Tree(['name' => 'discarded']))->makeRoot(),
            'Root inside savepoint must be created.',
        );

        $savepoint->rollBack();

        self::assertSame(
            0,
            (int) Tree::find()->count(),
            'Savepoint rollback must discard the root.',
        );
        self::assertSame(
            1,
            (int) $this->getDb()->createCommand('SELECT COUNT(*) FROM [[nested_sets_lock]]')->queryScalar(),
            'Registration must be rolled back.',
        );
        self::assertTrue(
            (new Tree(['name' => 'kept']))->makeRoot(),
            'Root after rollback must be created.',
        );

        $outer->commit();

        self::assertSame(
            ['kept'],
            Tree::find()->select('name')->column(),
            'Only the kept root may persist.',
        );
    }

    public function testTerminatedWriterReleasesTheDatabaseLock(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $gate = "{$this->directory}/never-release";

        $first = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'outer' => true,
                'hold' => 'after-method',
                'gate' => $gate,
            ],
        );

        $first->await('held');
        $first->close();

        $this->assertWorkerSucceeded(
            $this->start(
                [
                    'operation' => 'append',
                    'target' => $root->getAttributes(),
                    'name' => 'after-exit',
                ],
            )->await('finished'),
            'Next writer must acquire the released lock.',
        );
        self::assertSame(
            ['root', 'after-exit'],
            Tree::find()->orderBy('lft')->select('name')->column(),
            'Terminated writer changes must not persist.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenLockBootstrapIsMissing(): void
    {
        $this->getDb()->createCommand()->delete('nested_sets_lock')->execute();

        try {
            (new Tree(['name' => 'root']))->makeRoot();

            self::fail(
                'Missing migration data must fail before writing a root.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                Message::LOCK_BOOTSTRAP_ROW_MISSING->getMessage(),
                $exception->getMessage(),
                'Message must name the missing bootstrap row.',
            );
            self::assertSame(
                0,
                (int) Tree::find()->count(),
                'No root may be written.',
            );
            self::assertNull(
                $this->getDb()->getTransaction(),
                'No transaction may remain open.',
            );
        }
    }

    public function testThrowInvalidConfigExceptionWhenOrdinaryDeleteRunsWithoutTransaction(): void
    {
        $root = new NonTransactionalTree(['name' => 'root']);

        $root->makeRoot();

        $node = new NonTransactionalTree(['name' => 'node']);

        $node->appendTo($root);

        try {
            $node->delete();

            self::fail(
                'A nontransactional delete must be rejected before writing.',
            );
        } catch (InvalidConfigException) {
            self::assertSame(
                2,
                (int) Tree::find()->count(),
                'Rejected delete must keep both nodes.',
            );
        }

        $transaction = $this
            ->getDb()
            ->beginTransaction();

        self::assertSame(
            1,
            $node->delete(),
            'Delete inside a transaction must remove one row.',
        );

        $transaction->rollBack();

        self::assertSame(
            2,
            (int) Tree::find()->count(),
            'Rollback must restore the deleted node.',
        );
    }

    public function testTimeoutRestoresSettingsAndKeepsTheOuterTransactionUsable(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $gate = "{$this->directory}/release";
        $secondGate = "{$this->directory}/start-second";

        $second = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'outer' => true,
                'recover' => true,
                'timeoutSetting' => 23,
                'waitBefore' => true,
                'gate' => $secondGate,
            ],
        );

        $second->await('prepared');

        $first = $this->start(
            [
                'operation' => 'append',
                'target' => $root->getAttributes(),
                'outer' => true,
                'hold' => 'after-method',
                'gate' => $gate,
            ],
        );

        $first->await('held');

        touch($secondGate);

        $result = $second->await('finished');

        touch($gate);

        $this->assertWorkerSucceeded(
            $first->await('finished'),
            'Holding writer must commit.',
        );

        $this->assertLockFailure($result);

        self::assertTrue(
            $result['transactionActive'] ?? false,
            'Yii transaction must stay active.',
        );
        self::assertTrue(
            $result['nativeActive'] ?? false,
            'Native transaction must stay active.',
        );
        self::assertTrue(
            $result['recovered'] ?? false,
            'An outer transaction must be usable after the failed operation savepoint is rolled back.',
        );
    }

    public function testWriterLockPrecedesValidationEvenWhenValidationFails(): void
    {
        $gate = "{$this->directory}/start-second";

        $second = $this->start(
            [
                'waitBefore' => true,
                'gate' => $gate,
            ],
        );

        $second->await('prepared');

        $root = new Tree(['name' => '']);

        $root->on(
            Tree::EVENT_BEFORE_VALIDATE,
            function () use ($gate, $second): void {
                touch($gate);

                $this->assertLockFailure($second->await('finished'));
            },
        );

        self::assertFalse(
            $root->makeRoot(),
            'Invalid root must not be created.',
        );
        self::assertSame(
            0,
            (int) Tree::find()->count(),
            'No root may be written.',
        );
    }

    /**
     * @param array<string, mixed> $message
     */
    private function assertLockFailure(array $message): void
    {
        self::assertFalse(
            $message['success'] ?? false,
            'Blocked writer must fail.',
        );

        $exception = $message['exception'] ?? null;

        self::assertIsString(
            $exception,
            'Failure must report its exception class.',
        );
        self::assertTrue(
            is_a($exception, Exception::class, true),
            'Expected a database lock failure rather than a worker/configuration error. Reported: '
            . json_encode($message, JSON_THROW_ON_ERROR),
        );

        $text = $message['message'] ?? null;

        self::assertIsString(
            $text,
            'Failure must report its message.',
        );
        self::assertMatchesRegularExpression(
            '/lock|timeout|deadlock|resource busy/i',
            $text,
            'Failure must be caused by lock contention. Reported: ' . json_encode($message, JSON_THROW_ON_ERROR),
        );
        self::assertSame(
            $message['timeoutBefore'] ?? null,
            $message['timeoutAfter'] ?? null,
            'Lock wait configuration must be restored after failure.',
        );
    }

    /**
     * @param array<string, mixed> $message
     */
    private function assertWorkerSucceeded(array $message, string $expectation): void
    {
        self::assertTrue(
            $message['success'] ?? false,
            $expectation . ' Reported: ' . json_encode($message, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function start(array $payload): ConcurrentProcess
    {
        $worker = new ConcurrentProcess(['db' => $this->connection] + $payload, $this->directory);

        $this->workers[] = $worker;

        return $worker;
    }
}
