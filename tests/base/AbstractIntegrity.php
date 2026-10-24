<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use Error;
use RuntimeException;
use yii\base\{ModelEvent, NotSupportedException};
use yii\db\{ActiveRecord, Exception, IntegrityException, StaleObjectException};
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{
    JoinedTree,
    LockedTree,
    MultipleTree,
    NonTransactionalTree,
    StorageValueTree,
    Tree,
};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Unit tests for the {@see NestedSetsBehavior} tree invariants after stale, repeated and failed operations.
 */
abstract class AbstractIntegrity extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createDatabase();
    }

    public function testAfterInsertListenerCanSaveTheRoot(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->on(
            ActiveRecord::EVENT_AFTER_INSERT,
            static function () use ($root): void {
                $root->setAttribute('name', 'renamed');

                self::assertTrue(
                    $root->save(false, ['name']),
                    'Listener save must succeed.',
                );
            },
        );

        self::assertTrue(
            $root->makeRoot(),
            'Root must be created.',
        );
        self::assertSame(
            'renamed',
            Tree::findOne($root->getPrimaryKey())?->getAttribute('name'),
            'Listener rename must persist.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testAfterUpdateListenerCanDeleteTargetAndSaveOwner(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $first = new Tree(['name' => 'first']);

        $first->appendTo($root);

        $target = new Tree(['name' => 'target']);

        $target->appendTo($root);

        $handled = false;

        $first->on(
            ActiveRecord::EVENT_AFTER_UPDATE,
            static function () use ($first, $target, &$handled): void {
                if ($handled) {
                    return;
                }

                $handled = true;

                self::assertSame(
                    1,
                    $target->delete(),
                    'Listener must delete the target.',
                );

                $first->setAttribute('name', 'renamed');

                self::assertTrue(
                    $first->save(false, ['name']),
                    'Listener save must succeed.',
                );
            },
        );

        self::assertTrue(
            $first->appendTo($target),
            'Move must succeed.',
        );
        self::assertSame(
            1,
            $first->getAttribute('depth'),
            'Owner must end up under the root.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testCoordinateReloadSupportsAliasedQueriesWithJoins(): void
    {
        $root = new JoinedTree(['name' => 'root']);

        self::assertTrue(
            $root->makeRoot(),
            'Root must be created.',
        );

        $child = new JoinedTree(['name' => 'child']);

        self::assertTrue(
            $child->appendTo($root),
            'Child must be appended.',
        );
        self::assertSame(
            1,
            $child->deleteWithChildren(),
            'Exactly one row must be deleted.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testExistingNodeMovesToTheTargetsCurrentTree(): void
    {
        $firstRoot = new MultipleTree(['name' => 'first-root']);

        $firstRoot->makeRoot();

        $secondRoot = new MultipleTree(['name' => 'second-root']);

        $secondRoot->makeRoot();

        $target = new MultipleTree(['name' => 'target']);

        $target->appendTo($firstRoot);

        $source = new MultipleTree(['name' => 'source']);

        $source->appendTo($firstRoot);

        $targetSnapshot = MultipleTree::findOne($target->getPrimaryKey());

        self::assertNotNull(
            $targetSnapshot,
            'Target snapshot must be loaded.',
        );

        $targetSnapshot->appendTo($secondRoot);

        self::assertTrue(
            $source->appendTo($target),
            'Move must succeed.',
        );
        self::assertSame(
            $secondRoot->getAttribute('tree'),
            $source->getAttribute('tree'),
            'Source must join the current target tree.',
        );
        self::assertSame(
            ['second-root', 'target'],
            $source->parents()->select('name')->column(),
            'Source ancestry must follow the moved target.',
        );

        $this->assertIntegrity(MultipleTree::class);
    }

    public function testFailedInsertRestoresBusinessValuesRatherThanStorageValues(): void
    {
        $root = new StorageValueTree(['name' => 'business-value']);

        $root->on(
            ActiveRecord::EVENT_BEFORE_INSERT,
            static function (ModelEvent $event): void {
                $event->isValid = false;
            },
        );

        self::assertFalse(
            $root->makeRoot(),
            'Vetoed insert must fail.',
        );
        self::assertSame(
            'business-value',
            $root->getAttribute('name'),
            'Business value must be restored.',
        );
        self::assertTrue(
            $root->getIsNewRecord(),
            'Owner must stay a new record.',
        );
        self::assertSame(
            0,
            (int) Tree::find()->count(),
            'No row may be written.',
        );
    }

    public function testFailedValidationDoesNotRetainMovement(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $first = new Tree(['name' => 'first']);

        $first->appendTo($root);

        $second = new Tree(['name' => 'second']);

        $second->appendTo($root);
        $first->setAttribute('name', '');

        self::assertFalse(
            $first->appendTo($second),
            'Invalid move must fail.',
        );

        $first->setAttribute('name', 'renamed');

        self::assertTrue(
            $first->save(),
            'Plain save must succeed.',
        );

        $this->assertIntegrity(Tree::class);

        self::assertSame(
            1,
            Tree::findOne($first->getPrimaryKey())?->getAttribute('depth'),
            'Node must stay at its original depth.',
        );
    }

    public function testInsertDiscardsPendingCoordinateChangesFromListeners(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->on(
            ActiveRecord::EVENT_AFTER_INSERT,
            static function () use ($root): void {
                $root->setAttribute('lft', 99);
            },
        );

        self::assertTrue(
            $root->makeRoot(),
            'Root must be created.',
        );
        self::assertSame(
            1,
            $root->getAttribute('lft'),
            'Left boundary must be the persisted value.',
        );
        self::assertSame(
            [],
            $root->getDirtyAttributes(),
            'No dirty attributes may remain.',
        );
    }

    public function testInsertKeepsBusinessValuesWhenDirtyAttributesUseStorageValues(): void
    {
        $root = new StorageValueTree(['name' => 'business-value']);

        self::assertTrue(
            $root->makeRoot(),
            'Root must be created.',
        );
        self::assertSame(
            'business-value',
            $root->getAttribute('name'),
            'Model must keep the business value.',
        );
        self::assertSame(
            'BUSINESS-VALUE',
            Tree::findOne($root->getPrimaryKey())?->getAttribute('name'),
            'Storage value must be persisted.',
        );
    }

    public function testInsertKeepsUnsavedBusinessAttributes(): void
    {
        $this
            ->getDb()
            ->createCommand()
            ->addColumn('tree', 'note', (string) $this->string()->defaultValue('db-note'))
            ->execute();
        $this
            ->getDb()
            ->getSchema()
            ->refreshTableSchema('tree');

        $root = new Tree(['name' => 'root']);

        $root->setAttribute('note', 'pending');

        self::assertTrue(
            $root->makeRoot(true, ['name']),
            'Root must be created.',
        );
        self::assertSame(
            'pending',
            $root->getAttribute('note'),
            'Unsaved value must be kept.',
        );
        self::assertSame(
            ['note' => 'pending'],
            $root->getDirtyAttributes(),
            'Unsaved attribute must stay dirty.',
        );
        self::assertSame(
            'db-note',
            Tree::findOne($root->getPrimaryKey())?->getAttribute('note'),
            'Database default must be persisted.',
        );
    }

    public function testInsertVetoAfterBoundaryShiftRollsBack(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $child = new NonTransactionalTree(['name' => 'child']);

        $veto = static function (ModelEvent $event): void {
            $event->isValid = false;
        };

        $child->on(
            ActiveRecord::EVENT_BEFORE_INSERT,
            $veto,
        );

        $attributes = $child->getDirtyAttributes();

        $before = Tree::find()
            ->asArray()
            ->all();

        self::assertFalse(
            $child->appendTo($root),
            'Vetoed insert must fail.',
        );
        self::assertSame(
            $attributes,
            $child->getDirtyAttributes(),
            'Dirty attributes must be restored.',
        );
        self::assertSame(
            $before,
            Tree::find()->asArray()->all(),
            'Boundary shift must be rolled back.',
        );

        $child->off(
            ActiveRecord::EVENT_BEFORE_INSERT,
            $veto,
        );

        self::assertTrue(
            $child->appendTo($root),
            'Retried insert must succeed.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testInsertWithSelectedAttributesPersistsBoundaries(): void
    {
        $root = new Tree(['name' => 'root']);

        self::assertTrue(
            $root->makeRoot(true, ['name']),
            'Root must be created.',
        );

        $child = new Tree(['name' => 'child']);

        self::assertTrue(
            $child->appendTo($root, true, ['name']),
            'Child must be appended.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testMoveListenersSeeCurrentCoordinates(): void
    {
        $root = new MultipleTree(['name' => 'root']);

        $root->makeRoot();

        $first = new MultipleTree(['name' => 'first']);

        $first->appendTo($root);

        $target = new MultipleTree(['name' => 'target']);

        $target->appendTo($root);
        $first->on(
            ActiveRecord::EVENT_AFTER_UPDATE,
            static function () use ($first): void {
                $persisted = MultipleTree::findOne($first->getPrimaryKey());

                self::assertNotNull($persisted, 'Moved node must be persisted.');
                self::assertSame(
                    $persisted->getAttributes(),
                    $first->getAttributes(),
                    'Owner must match the persisted coordinates.',
                );
            },
        );

        self::assertTrue(
            $first->appendTo($target),
            'Move must succeed.',
        );
        self::assertTrue(
            $first->makeRoot(),
            'Root promotion must succeed.',
        );

        $this->assertIntegrity(MultipleTree::class);
    }

    public function testMoveRollsBackAfterErrorAndPreservesUnsavedAttributes(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $first = new Tree(['name' => 'first']);

        $first->appendTo($root);

        $second = new Tree(['name' => 'second']);

        $second->appendTo($root);
        $first->setAttribute('name', 'renamed');

        $attributes = $first->getAttributes();

        $fail = static function (): never {
            throw new Error('Injected failure');
        };

        $first->on(
            ActiveRecord::EVENT_AFTER_UPDATE,
            $fail,
        );

        $before = Tree::find()
            ->asArray()
            ->all();

        try {
            $first->appendTo($second);

            self::fail(
                'The listener must fail.',
            );
        } catch (Error $e) {
            self::assertSame(
                'Injected failure',
                $e->getMessage(),
                'Injected error must propagate.',
            );
        }

        self::assertSame(
            $before,
            Tree::find()->asArray()->all(),
            'Move must be rolled back.',
        );
        self::assertSame(
            $attributes,
            $first->getAttributes(),
            'Owner attributes must be restored.',
        );
        self::assertSame(
            'renamed',
            $first->getAttribute('name'),
            'Unsaved name must be kept.',
        );

        $first->off(ActiveRecord::EVENT_AFTER_UPDATE, $fail);

        self::assertTrue(
            $first->appendTo($second),
            'Retried move must succeed.',
        );
        self::assertSame(
            'renamed',
            Tree::findOne($first->getPrimaryKey())?->getAttribute('name'),
            'Unsaved name must persist with the retry.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testNestedRollbackKeepsTheCallersTransactionActive(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $transaction = $this
            ->getDb()
            ->beginTransaction();

        $child = new Tree(['name' => 'child']);

        $child->appendTo($root);

        $veto = static function (ModelEvent $event): void {
            $event->isValid = false;
        };

        $child->on(
            ActiveRecord::EVENT_BEFORE_DELETE,
            $veto,
        );

        self::assertFalse(
            $child->deleteWithChildren(),
            'Vetoed deletion must fail.',
        );
        self::assertTrue(
            $transaction->getIsActive(),
            'Caller transaction must stay active.',
        );
        self::assertSame(
            1,
            $transaction->getLevel(),
            'Transaction level must be restored.',
        );

        $child->off(ActiveRecord::EVENT_BEFORE_DELETE, $veto);

        self::assertSame(
            1,
            $child->deleteWithChildren(),
            'Retried deletion must remove one row.',
        );

        $transaction->rollBack();

        $this->assertIntegrity(Tree::class);
    }

    public function testNewNodesLoadDefaultsBeforeOptimisticMoves(): void
    {
        $this
            ->getDb()
            ->createCommand()
            ->addColumn('tree', 'revision', (string) $this->integer()->notNull()->defaultValue(0))
            ->execute();
        $this
            ->getDb()
            ->getSchema()
            ->refreshTableSchema('tree');

        $root = new LockedTree(['name' => 'root']);

        self::assertTrue(
            $root->makeRoot(),
            'Root must be created.',
        );
        self::assertSame(
            0,
            $root->getAttribute('revision'),
            'Root revision must load the default.',
        );

        $first = new LockedTree(['name' => 'first']);

        $first->appendTo($root);

        $second = new LockedTree(['name' => 'second']);

        $second->appendTo($root);

        self::assertSame(
            0,
            $first->getAttribute('revision'),
            'Child revision must load the default.',
        );
        self::assertTrue(
            $first->appendTo($second),
            'Optimistic move must succeed.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testNonTransactionalInsertRollsBackOnSqlFailureAndCanBeRetried(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $child = new NonTransactionalTree(['name' => 'child']);

        $child->setAttribute('id', $root->getPrimaryKey());

        $attributes = $child->getAttributes();

        $before = Tree::find()
            ->asArray()
            ->all();

        try {
            $child->appendTo($root);

            self::fail(
                'A duplicate primary key must fail.',
            );
        } catch (IntegrityException) {
            self::assertSame(
                $before,
                Tree::find()->asArray()->all(),
                'Boundary shift must be rolled back.',
            );
            self::assertTrue(
                $child->getIsNewRecord(),
                'Owner must stay a new record.',
            );
            self::assertSame(
                $attributes,
                $child->getAttributes(),
                'Owner attributes must be restored.',
            );
        }

        unset($child->id);

        self::assertTrue(
            $child->appendTo($root),
            'Retried insert must succeed.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testNormalDeleteRefreshesCoordinatesAndPromotesChildren(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $first = new Tree(['name' => 'first']);

        $first->appendTo($root);

        $second = new Tree(['name' => 'second']);

        $second->appendTo($root);

        $child = new Tree(['name' => 'child']);

        $child->appendTo($second);
        $first->delete();

        self::assertSame(
            1,
            $second->delete(),
            'Stale node must delete exactly one row.',
        );
        self::assertSame(
            ['root', 'child'],
            Tree::find()->orderBy('lft')->select('name')->column(),
            'Child must be promoted to the root.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testRefreshInvalidatesCachedCoordinates(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        self::assertTrue(
            $root->isLeaf(),
            'New root must be a leaf.',
        );

        $snapshot = Tree::findOne($root->getPrimaryKey());

        self::assertNotNull(
            $snapshot,
            'Root snapshot must be loaded.',
        );

        (new Tree(['name' => 'child']))->appendTo($snapshot);

        self::assertTrue(
            $root->refresh(),
            'Refresh must succeed.',
        );
        self::assertFalse(
            $root->isLeaf(),
            'Refreshed root must not be a leaf.',
        );
        self::assertSame(
            1,
            (int) $root->children()->count(),
            'Refreshed root must see one child.',
        );
    }

    public function testReinsertedDeletedNodeExposesLeafStateBeforeInsert(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $parent = new Tree(['name' => 'parent']);

        $parent->appendTo($root);

        (new Tree(['name' => 'child']))->appendTo($parent);

        self::assertSame(
            1,
            $parent->delete(),
            'Delete must remove one row.',
        );

        unset($parent->id);

        $parent->on(
            ActiveRecord::EVENT_BEFORE_INSERT,
            static function () use ($parent): void {
                self::assertTrue($parent->isLeaf(), 'Pending node must report a leaf.');
            },
        );

        self::assertTrue(
            $parent->appendTo($root),
            'Reinsert must succeed.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testRepeatedMovesAndInsertionsPreserveStructure(): void
    {
        foreach ([Tree::class, MultipleTree::class] as $class) {
            $root = new $class(['name' => 'root']);

            self::assertTrue(
                $root->makeRoot(),
                'Root must be created.',
            );

            $first = new $class(['name' => 'first']);

            self::assertTrue(
                $first->appendTo($root),
                'First child must be appended.',
            );

            $second = new $class(['name' => 'second']);

            self::assertTrue(
                $second->appendTo($root),
                'Second child must be appended.',
            );
            self::assertTrue(
                $first->isLeaf(),
                'First child must be a leaf.',
            );
            self::assertTrue(
                $first->appendTo($second),
                'Move under sibling must succeed.',
            );
            self::assertTrue(
                $first->prependTo($root),
                'Move back to root must succeed.',
            );

            $child = new $class(['name' => 'child']);

            self::assertTrue(
                $child->appendTo($first),
                'Grandchild must be appended.',
            );

            $this->assertIntegrity($class);

            $root->refresh();

            $this->assertQueryHasOrderBy($root->children(), 'children');

            self::assertSame(
                ['first', 'child', 'second'],
                $root->children()->select('name')->column(),
                'Descendants must be in boundary order.',
            );
        }
    }

    public function testStaleSubtreeDeletionDoesNotDeleteShiftedSiblings(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $first = new Tree(['name' => 'first']);

        $first->appendTo($root);

        $second = new Tree(['name' => 'second']);

        $second->appendTo($root);

        $third = new Tree(['name' => 'third']);

        $third->appendTo($root);
        $first->deleteWithChildren();

        self::assertSame(
            1,
            $second->deleteWithChildren(),
            'Stale subtree must delete exactly one row.',
        );
        self::assertSame(
            ['root', 'third'],
            Tree::find()->orderBy('lft')->select('name')->column(),
            'Shifted sibling must survive.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testStaleTargetRevisionDoesNotBlockMovingCurrentOwner(): void
    {
        $this
            ->getDb()
            ->createCommand()
            ->addColumn('tree', 'revision', (string) $this->integer()->notNull()->defaultValue(0))
            ->execute();
        $this
            ->getDb()
            ->getSchema()
            ->refreshTableSchema('tree');

        $root = new LockedTree(['name' => 'root']);

        $root->makeRoot();

        $node = new LockedTree(['name' => 'node']);

        $node->appendTo($root);

        $target = new LockedTree(['name' => 'target']);

        $target->appendTo($root);

        LockedTree::updateAll(['revision' => 1], ['id' => $target->getPrimaryKey()]);

        self::assertTrue(
            $node->appendTo($target),
            'Move under a stale target must succeed.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testStaleTargetUsesCurrentTreeAndPosition(): void
    {
        $first = new MultipleTree(['name' => 'first']);

        $first->makeRoot();

        $second = new MultipleTree(['name' => 'second']);

        $second->makeRoot();

        $target = new MultipleTree(['name' => 'target']);

        $target->appendTo($first);

        $other = MultipleTree::findOne($target->getPrimaryKey());

        self::assertNotNull(
            $other,
            'Target snapshot must be loaded.',
        );

        $other->appendTo($second);

        $child = new MultipleTree(['name' => 'child']);

        self::assertTrue(
            $child->appendTo($target),
            'Append to a stale target must succeed.',
        );

        $this->assertIntegrity(MultipleTree::class);

        self::assertSame(
            $second->getAttribute('tree'),
            $child->getAttribute('tree'),
            'Child must join the current target tree.',
        );
    }

    public function testStructuralMoveAcceptsAnOptimisticLockLoadedAsString(): void
    {
        $this
            ->getDb()
            ->createCommand()
            ->addColumn('tree', 'revision', (string) $this->integer()->notNull()->defaultValue(0))
            ->execute();
        $this
            ->getDb()
            ->getSchema()
            ->refreshTableSchema('tree');

        $root = new LockedTree(['name' => 'root']);

        $root->makeRoot();

        $node = new LockedTree(['name' => 'node']);

        $node->appendTo($root);

        $target = new LockedTree(['name' => 'target']);

        $target->appendTo($root);

        // Request data assigned through load() or setAttributes() carries the version as a numeric string.
        $node->setAttribute('revision', (string) $node->getAttribute('revision'));

        self::assertTrue(
            $node->appendTo($target),
            'Numeric string version must match the stored integer.',
        );

        $stored = LockedTree::findOne($node->getPrimaryKey());

        self::assertInstanceOf(
            LockedTree::class,
            $stored,
            'Moved node must still be persisted.',
        );
        self::assertSame(
            1,
            $stored->getAttribute('revision'),
            'Move must advance the stored revision once.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testSubtreeDeletionRollsBackAfterListenerFailure(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $child = new Tree(['name' => 'child']);

        $child->appendTo($root);

        $before = Tree::find()
            ->asArray()
            ->all();

        $fail = static function (): never {
            throw new RuntimeException('Injected failure');
        };

        $child->on(
            ActiveRecord::EVENT_AFTER_DELETE,
            $fail,
        );

        try {
            $child->deleteWithChildren();

            self::fail(
                'The listener must fail.',
            );
        } catch (RuntimeException $e) {
            self::assertSame(
                'Injected failure',
                $e->getMessage(),
                'Injected error must propagate.',
            );
        }

        self::assertSame(
            $before,
            Tree::find()->asArray()->all(),
            'Deletion must be rolled back.',
        );
        self::assertFalse(
            $child->getIsNewRecord(),
            'Owner must stay persisted.',
        );

        $child->off(
            ActiveRecord::EVENT_AFTER_DELETE,
            $fail,
        );

        self::assertSame(
            1,
            $child->deleteWithChildren(),
            'Retried deletion must remove one row.',
        );

        $this->assertIntegrity(Tree::class);
    }

    public function testTargetsAndNewRootsHaveSynchronizedAttributes(): void
    {
        $root = new MultipleTree(['name' => 'root']);

        $root->makeRoot();

        self::assertEquals(
            $root->getPrimaryKey(),
            $root->getOldAttribute('tree'),
            'Old tree attribute must match the primary key.',
        );
        self::assertSame(
            [],
            $root->getDirtyAttributes(),
            'No dirty attributes may remain.',
        );
        self::assertTrue(
            $root->isLeaf(),
            'New root must be a leaf.',
        );

        $root->setAttribute('name', 'pending');

        (new MultipleTree(['name' => 'child']))->appendTo($root);

        self::assertFalse(
            $root->isLeaf(),
            'Target must see its new child.',
        );
        self::assertSame(
            ['name' => 'pending'],
            $root->getDirtyAttributes(),
            'Unsaved name must stay dirty.',
        );

        $this->assertIntegrity(MultipleTree::class);
    }

    public function testThrowExceptionWhenTargetUsesAnotherTable(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $target = new MultipleTree(['name' => 'target']);

        $target->makeRoot();

        $before = Tree::find()->asArray()->all();

        try {
            (new Tree(['name' => 'child']))->appendTo($target);

            self::fail(
                'A target from another table must be rejected.',
            );
        } catch (Exception) {
            self::assertSame(
                $before,
                Tree::find()->asArray()->all(),
                'Tree must stay unchanged.',
            );
        }
    }

    public function testThrowNotSupportedExceptionWhenRootDeleteFollowsAbortedSubtreeDeletion(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $veto = static function (ModelEvent $event): void {
            $event->isValid = false;
        };

        $root->on(
            ActiveRecord::EVENT_BEFORE_DELETE,
            $veto,
        );

        self::assertFalse(
            $root->deleteWithChildren(),
            'Vetoed deletion must fail.',
        );

        $root->off(
            ActiveRecord::EVENT_BEFORE_DELETE,
            $veto,
        );

        $this->expectException(NotSupportedException::class);

        $root->delete();
    }

    public function testThrowStaleObjectExceptionWhenDeletingAnAlreadyDeletedSnapshot(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $child = new Tree(['name' => 'child']);

        $child->appendTo($root);

        $snapshot = Tree::findOne($child->getPrimaryKey());

        self::assertNotNull(
            $snapshot,
            'Child snapshot must be loaded.',
        );

        $child->deleteWithChildren();

        (new Tree(['name' => 'replacement']))->appendTo($root);

        $before = Tree::find()
            ->asArray()
            ->all();

        try {
            $snapshot->deleteWithChildren();

            self::fail(
                'An already deleted snapshot must be rejected.',
            );
        } catch (StaleObjectException) {
            self::assertSame(
                $before,
                Tree::find()->asArray()->all(),
                'Tree must stay unchanged.',
            );
        }
    }

    public function testThrowStaleObjectExceptionWhenMovingAnUnchangedStaleRecord(): void
    {
        $this
            ->getDb()
            ->createCommand()
            ->addColumn('tree', 'revision', (string) $this->integer()->notNull()->defaultValue(0))
            ->execute();
        $this
            ->getDb()
            ->getSchema()
            ->refreshTableSchema('tree');

        $root = new LockedTree(['name' => 'root']);

        $root->makeRoot();

        $first = new LockedTree(['name' => 'first']);

        $first->appendTo($root);

        $second = new LockedTree(['name' => 'second']);

        $second->appendTo($root);

        LockedTree::updateAll(['revision' => 1], ['id' => $first->getPrimaryKey()]);

        $before = LockedTree::find()
            ->asArray()
            ->all();

        $called = false;

        $first->on(
            ActiveRecord::EVENT_BEFORE_UPDATE,
            static function () use (&$called): void {
                $called = true;
            },
        );

        try {
            $first->appendTo($second);

            self::fail(
                'A move with a changed optimistic lock must be rejected.',
            );
        } catch (StaleObjectException) {
            self::assertSame(
                $before,
                LockedTree::find()->asArray()->all(),
                'Tree must stay unchanged.',
            );
            self::assertFalse(
                $called,
                'Stale moves must stop before application update handlers run.',
            );
        }
    }

    public function testThrowStaleObjectExceptionWhenOlderSnapshotMovesAfterStructuralMove(): void
    {
        $this
            ->getDb()
            ->createCommand()
            ->addColumn('tree', 'revision', (string) $this->integer()->notNull()->defaultValue(0))
            ->execute();
        $this
            ->getDb()
            ->getSchema()
            ->refreshTableSchema('tree');

        $root = new LockedTree(['name' => 'root']);

        $root->makeRoot();

        $node = new LockedTree(['name' => 'node']);

        $node->appendTo($root);

        $target = new LockedTree(['name' => 'target']);

        $target->appendTo($root);

        $snapshot = LockedTree::findOne($node->getPrimaryKey());

        self::assertNotNull(
            $snapshot,
            'Node snapshot must be loaded.',
        );
        self::assertTrue(
            $node->appendTo($target, true, ['name']),
            'Structural move must succeed.',
        );
        self::assertSame(
            1,
            $node->getAttribute('revision'),
            'Structural move must advance the revision.',
        );

        $before = LockedTree::find()
            ->asArray()
            ->all();

        try {
            $snapshot->prependTo($root);

            self::fail(
                'An older source revision must not move a subtree again.',
            );
        } catch (StaleObjectException) {
            self::assertSame(
                $before,
                LockedTree::find()->asArray()->all(),
                'Tree must stay unchanged.',
            );
        }
    }

    public function testThrowStaleObjectExceptionWhenSubtreeLockHasChanged(): void
    {
        $this
            ->getDb()
            ->createCommand()
            ->addColumn('tree', 'revision', (string) $this->integer()->notNull()->defaultValue(0))
            ->execute();
        $this
            ->getDb()
            ->getSchema()
            ->refreshTableSchema('tree');

        $root = new LockedTree(['name' => 'root']);

        $root->makeRoot();

        $child = new LockedTree(['name' => 'child']);

        $child->appendTo($root);

        LockedTree::updateAll(['revision' => 1], ['id' => $child->getPrimaryKey()]);

        $before = LockedTree::find()
            ->asArray()
            ->all();

        try {
            $child->deleteWithChildren();

            self::fail(
                'A subtree with a changed optimistic lock must be rejected.',
            );
        } catch (StaleObjectException) {
            self::assertSame(
                $before,
                LockedTree::find()->asArray()->all(),
                'Tree must stay unchanged.',
            );
        }
    }

    public function testThrowStaleObjectExceptionWhenTargetWasDeleted(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $target = new Tree(['name' => 'target']);

        $target->appendTo($root);

        Tree::findOne($target->getPrimaryKey())?->deleteWithChildren();

        $before = Tree::find()
            ->asArray()
            ->all();

        try {
            (new Tree(['name' => 'child']))->appendTo($target);

            self::fail(
                'A deleted target must be rejected.',
            );
        } catch (StaleObjectException) {
            self::assertSame(
                $before,
                Tree::find()->asArray()->all(),
                'Tree must stay unchanged.',
            );
        }
    }

    public function testValidationRefreshDoesNotCancelTheRequestedMove(): void
    {
        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        $first = new Tree(['name' => 'first']);

        $first->appendTo($root);

        $second = new Tree(['name' => 'second']);

        $second->appendTo($root);
        $first->on(
            ActiveRecord::EVENT_BEFORE_VALIDATE,
            static function () use ($first): void {
                $first->refresh();
            },
        );

        self::assertTrue(
            $first->appendTo($second),
            'Move must succeed.',
        );
        self::assertSame(
            2,
            $first->getAttribute('depth'),
            'Node must end up under the sibling.',
        );

        $this->assertIntegrity(Tree::class);
    }

    /**
     * @param class-string<Tree|MultipleTree> $class
     */
    private function assertIntegrity(string $class): void
    {
        $trees = [];

        foreach ($class::find()->orderBy('lft')->all() as $node) {
            $key = $node instanceof MultipleTree ? $node->getAttribute('tree') : 0;

            $trees[$key][] = $node;
        }

        foreach ($trees as $nodes) {
            $boundaries = [];
            $stack = [];

            foreach ($nodes as $node) {
                $left = $node->getAttribute('lft');
                $right = $node->getAttribute('rgt');

                self::assertGreaterThan(
                    $left,
                    $right,
                    'Right boundary must exceed the left boundary.',
                );

                while ($stack !== [] && $left > $stack[array_key_last($stack)]) {
                    array_pop($stack);
                }

                self::assertSame(
                    count($stack),
                    $node->getAttribute('depth'),
                    'Depth must match the ancestor count.',
                );

                if ($stack !== []) {
                    self::assertLessThan(
                        $stack[array_key_last($stack)],
                        $right,
                        'Node must be nested within its parent.',
                    );
                }

                $stack[] = $right;
                $boundaries[] = $left;
                $boundaries[] = $right;
            }

            sort($boundaries);

            self::assertSame(
                range(1, count($nodes) * 2),
                $boundaries,
                'Boundaries must stay contiguous.',
            );
            self::assertSame(
                count($nodes) * 2,
                $nodes[0]->getAttribute('rgt'),
                'Root must enclose every node.',
            );
        }
    }
}
