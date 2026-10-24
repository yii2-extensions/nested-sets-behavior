<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use LogicException;
use RuntimeException;
use Throwable;
use yii\base\{Behavior, NotSupportedException};
use yii\db\{ActiveQuery, ActiveRecord, Connection, Exception, StaleObjectException};
use yii2\extensions\nestedsets\exception\Message;

use function array_key_exists;
use function is_scalar;

/**
 * Manages hierarchical data in {@see ActiveRecord} models using the nested sets pattern.
 *
 * Maintains the left, right and depth attributes of each node and, when {@see $treeAttribute} is set, a tree identifier
 * that stores several trees in one table. Structural writes serialize on a per-table writer lock held until the
 * outermost transaction ends, so the connection must enable savepoints and the `nested_sets_lock` registry table must
 * exist. A plain `delete()` acquires that lock in {@see beforeDelete()} and therefore requires an active Yii
 * transaction, for example through {@see ActiveRecord::transactions()}.
 *
 * @template T of ActiveRecord
 *
 * @extends Behavior<T>
 *
 * @property int $depth
 * @property int $lft
 * @property int $rgt
 */
class NestedSetsBehavior extends Behavior
{
    /**
     * Identifies the operation that places the owner as the last child of a target node.
     */
    public const string OPERATION_APPEND_TO = 'appendTo';
    /**
     * Identifies the operation that deletes the owner together with all its descendants.
     */
    public const string OPERATION_DELETE_WITH_CHILDREN = 'deleteWithChildren';
    /**
     * Identifies the operation that places the owner as the next sibling of a target node.
     */
    public const string OPERATION_INSERT_AFTER = 'insertAfter';
    /**
     * Identifies the operation that places the owner as the previous sibling of a target node.
     */
    public const string OPERATION_INSERT_BEFORE = 'insertBefore';
    /**
     * Identifies the operation that creates the owner as a root or promotes it to the root of a new tree.
     */
    public const string OPERATION_MAKE_ROOT = 'makeRoot';
    /**
     * Identifies the operation that places the owner as the first child of a target node.
     */
    public const string OPERATION_PREPEND_TO = 'prependTo';

    /**
     * Name of the attribute that stores the node depth, `0` for a root.
     *
     * @var 'depth' attribute name.
     */
    public string $depthAttribute = 'depth';
    /**
     * Name of the attribute that stores the left boundary of the node.
     *
     * @var 'lft' attribute name.
     */
    public string $leftAttribute = 'lft';
    /**
     * Maximum wait in seconds for each guard-row acquisition; valid range is 1 through 3600.
     */
    public int $lockTimeout = 10;
    /**
     * Name of the attribute that stores the right boundary of the node.
     *
     * @var 'rgt' attribute name.
     */
    public string $rightAttribute = 'rgt';
    /**
     * Name of the attribute that stores the tree identifier, or `false` to keep a single tree per table.
     */
    public string|false $treeAttribute = false;

    /**
     * Cached depth of the owner, or `null` when it must be read from the owner attributes.
     */
    protected int|null $depthValue = null;
    /**
     * Cached left value of the owner, or `null` when it must be read from the owner attributes.
     */
    protected int|null $leftValue = null;
    /**
     * Target node of the pending operation, or `null` when the operation has no target.
     *
     * @template T of NestedSetsBehavior
     *
     * @var ActiveRecord<T>|null
     */
    protected ActiveRecord|null $node = null;
    /**
     * Pending operation, one of the `OPERATION_*` constants, or `null` when no structural operation is pending.
     */
    protected string|null $operation = null;
    /**
     * Cached right value of the owner, or `null` when it must be read from the owner attributes.
     */
    protected int|null $rightValue = null;

    /**
     * Cached database connection of the owner model.
     */
    private Connection|null $db = null;

    /**
     * Acquires the table writer lock before application reads or raw SQL in an existing Yii transaction.
     *
     * The database retains the lock until that transaction commits or rolls back.
     *
     * @throws Throwable if no Yii transaction is active, savepoints are disabled, {@see $lockTimeout} is out of range,
     * the `nested_sets_lock` registry is missing, or the lock wait times out.
     */
    public function acquireTreeLock(): void
    {
        TreeLock::acquire($this->getOwner(), $this->lockTimeout);
    }

    /**
     * Closes the gap left by the deleted owner, moving the children of a node deleted on its own one level up.
     *
     * @throws Exception if updating the remaining coordinates fails.
     */
    public function afterDelete(): void
    {
        if ($this->operation === self::OPERATION_DELETE_WITH_CHILDREN || $this->getOwner()->isLeaf()) {
            $deltaValue = $this->getLeftValue() - $this->getRightValue() - 1;
        } else {
            $deltaValue = -2;

            $this->getOwner()::updateAll(
                QueryConditionBuilder::createOffsetUpdates(
                    $this->getDb(),
                    [
                        $this->depthAttribute => -1,
                        $this->leftAttribute => -1,
                        $this->rightAttribute => -1,
                    ],
                ),
                QueryConditionBuilder::createRangeCondition(
                    $this->leftAttribute,
                    $this->getLeftValue(),
                    $this->rightAttribute,
                    $this->getRightValue(),
                    $this->treeAttribute,
                    $this->getTreeValue($this->getOwner()),
                ),
            );
        }

        $this->shiftLeftRightAttribute(
            $this->getRightValue(),
            $deltaValue,
        );
        $this->invalidateCache();
    }

    /**
     * Assigns the primary key of a new root as its tree identifier when {@see $treeAttribute} is enabled.
     *
     * @throws Exception if the owner model has no primary key or the tree identifier update fails.
     */
    public function afterInsert(): void
    {
        if ($this->operation === self::OPERATION_MAKE_ROOT && $this->treeAttribute !== false) {
            $this->getOwner()->setAttribute($this->treeAttribute, $this->getOwner()->getPrimaryKey());

            $primaryKey = $this->getOwner()::primaryKey();

            if (isset($primaryKey[0]) === false) {
                throw new Exception(
                    Message::PRIMARY_KEY_REQUIRED->getMessage($this->getOwner()::class),
                );
            }

            $this->getOwner()::updateAll(
                [$this->treeAttribute => $this->getTreeValue($this->getOwner())],
                [$primaryKey[0] => $this->getTreeValue($this->getOwner())],
            );
        }

        $this->invalidateCache();
    }

    /**
     * Clears cached coordinates after a model refresh without cancelling an active operation.
     */
    public function afterRefresh(): void
    {
        $this->depthValue = null;
        $this->leftValue = null;
        $this->rightValue = null;
    }

    /**
     * Moves the owner subtree to the position requested by the pending operation and reloads the owner coordinates.
     *
     * @throws RuntimeException if the pending operation does not move the owner relative to a target node.
     * @throws StaleObjectException if the owner no longer exists when its coordinates are reloaded.
     */
    public function afterUpdate(): void
    {
        $currentOwnerTreeValue = $this->getTreeValue($this->getOwner());

        if ($this->operation === self::OPERATION_MAKE_ROOT) {
            $this->moveNodeAsRoot($currentOwnerTreeValue);
            $this->refreshStructure($this->getOwner());
        }

        if ($this->node === null) {
            $this->invalidateCache();

            return;
        }

        $context = $this->createMoveContext($this->node, $this->operation);

        $this->moveNode($context);
        $this->refreshStructure($this->getOwner());
        $this->invalidateCache();
    }

    /**
     * Appends the owner as the last child of the target node, inserting a new owner or moving an existing subtree.
     *
     * Runs in its own transaction, acquires the table writer lock, rolls back and restores the owner on failure, and
     * reloads the coordinates of the owner and the target on success.
     *
     * @param T $node Node that becomes the parent of the owner.
     * @param bool $runValidation Whether to validate the owner before saving.
     * @param list<string>|null $attributes Attributes to save, or `null` to save all attributes.
     *
     * @throws Throwable if a lifecycle handler or database operation fails.
     *
     * @return bool Whether the owner was saved.
     */
    public function appendTo(ActiveRecord $node, bool $runValidation = true, array|null $attributes = null): bool
    {
        return $this->executeOperation($node, self::OPERATION_APPEND_TO, $runValidation, $attributes);
    }

    /**
     * Acquires the table writer lock, reloads the owner coordinates, and rejects deleting a root node on its own.
     *
     * @throws Exception if the owner is a new record.
     * @throws NotSupportedException if the owner is a root and the deletion is not run by {@see deleteWithChildren()}.
     * @throws StaleObjectException if the owner no longer exists or its optimistic lock version has changed.
     * @throws Throwable if the writer lock cannot be acquired, for example without an active Yii transaction.
     */
    public function beforeDelete(): void
    {
        if ($this->getOwner()->getIsNewRecord()) {
            throw new Exception(
                Message::CANNOT_DELETE_NEW_RECORD->getMessage(),
            );
        }

        TreeLock::acquire($this->getOwner(), $this->lockTimeout);

        $this->refreshStructure($this->getOwner(), true);

        if ($this->operation !== self::OPERATION_DELETE_WITH_CHILDREN && $this->getOwner()->isRoot()) {
            throw new NotSupportedException(
                Message::DELETE_ROOT_NOT_SUPPORTED->getMessage($this->getOwner()::class),
            );
        }
    }

    /**
     * Reloads the target coordinates and assigns the coordinates of the new owner according to the pending operation.
     *
     * @throws Exception if the target node is a new record or uses another connection or table, if a sibling of a root
     * is requested, or if a second root is created while {@see $treeAttribute} is `false`.
     * @throws NotSupportedException if no supported operation is pending, for example on a plain `insert()`.
     * @throws StaleObjectException if the target node no longer exists.
     */
    public function beforeInsert(): void
    {
        if ($this->node?->getIsNewRecord() === true) {
            throw new Exception(
                Message::CANNOT_CREATE_NODE_TARGET_IS_NEW_RECORD->getMessage(),
            );
        }

        if ($this->node !== null) {
            $this->refreshTarget($this->node);
        }

        match (true) {
            $this->operation === self::OPERATION_APPEND_TO
                && $this->node !== null => $this->beforeInsertNodeWithContext(
                    NodeContext::forAppendTo($this->node, $this->rightAttribute),
                ),
            $this->operation === self::OPERATION_INSERT_AFTER
                && $this->node !== null => $this->beforeInsertNodeWithContext(
                    NodeContext::forInsertAfter($this->node, $this->rightAttribute),
                ),
            $this->operation === self::OPERATION_INSERT_BEFORE
                && $this->node !== null => $this->beforeInsertNodeWithContext(
                    NodeContext::forInsertBefore($this->node, $this->leftAttribute),
                ),
            $this->operation === self::OPERATION_MAKE_ROOT => $this->beforeInsertRootNode(),
            $this->operation === self::OPERATION_PREPEND_TO
                && $this->node !== null => $this->beforeInsertNodeWithContext(
                    NodeContext::forPrependTo($this->node, $this->leftAttribute),
                ),
            default => throw new NotSupportedException(
                Message::INSERT_NOT_SUPPORTED->getMessage($this->getOwner()::class),
            ),
        };
    }

    /**
     * Reloads the coordinates of the owner and the target and validates the pending move before the owner is updated.
     *
     * For structural moves, also marks the optimistic lock attribute dirty so that the update checks and increments it.
     *
     * @throws Exception if the move is invalid: promoting a root, making a root while {@see $treeAttribute} is `false`,
     * or targeting a new record, the owner itself, one of its descendants, or a root for sibling moves.
     * @throws StaleObjectException if the owner or the target no longer exists, or the owner optimistic lock version
     * has changed.
     */
    public function beforeUpdate(): void
    {
        // Structural moves acquire the writer lock in runOperation(); ordinary updates do not change coordinates.
        if ($this->operation !== null) {
            $this->refreshStructure($this->getOwner(), true);

            if ($this->node !== null && $this->node->getIsNewRecord() === false) {
                $this->refreshTarget($this->node);
            }
        }

        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                if ($this->treeAttribute === false) {
                    throw new Exception(
                        Message::CANNOT_MOVE_NODE_AS_ROOT->getMessage(),
                    );
                }

                if ($this->getOwner()->isRoot()) {
                    throw new Exception(
                        Message::CANNOT_MOVE_ROOT_AS_ROOT->getMessage(),
                    );
                }

                break;
            case self::OPERATION_INSERT_AFTER:
            case self::OPERATION_INSERT_BEFORE:
                if ($this->node?->isRoot() === true) {
                    throw new Exception(
                        Message::CANNOT_MOVE_NODE_TARGET_IS_ROOT->getMessage(),
                    );
                }
                // no break
            case self::OPERATION_APPEND_TO:
            case self::OPERATION_PREPEND_TO:
                if ($this->node?->getIsNewRecord() === true) {
                    throw new Exception(
                        Message::CANNOT_MOVE_NODE_TARGET_IS_NEW_RECORD->getMessage(),
                    );
                }

                if ($this->node !== null && $this->getOwner()->equals($this->node)) {
                    throw new Exception(
                        Message::CANNOT_MOVE_NODE_TARGET_IS_SAME->getMessage(),
                    );
                }

                if ($this->node !== null && $this->node->isChildOf($this->getOwner())) {
                    throw new Exception(
                        Message::CANNOT_MOVE_NODE_TARGET_IS_CHILD->getMessage(),
                    );
                }
        }

        $lock = $this->getOwner()->optimisticLock();

        if ($this->operation !== null && $lock !== null) {
            $this->getOwner()->markAttributeDirty($lock);
        }
    }

    /**
     * Returns a query for the descendants of the owner, ordered by left value and optionally limited in depth.
     *
     * @param int|null $depth Maximum number of levels below the owner, or `null` for all descendants.
     *
     * @return ActiveQuery<T> Query for the descendants of the owner.
     */
    public function children(int|null $depth = null): ActiveQuery
    {
        return $this->getOwner()::find()
            ->andWhere(
                QueryConditionBuilder::createChildrenCondition(
                    $this->leftAttribute,
                    $this->getLeftValue(),
                    $this->rightAttribute,
                    $this->getRightValue(),
                    $this->treeAttribute,
                    $this->getTreeValue($this->getOwner()),
                    $depth !== null ? $this->depthAttribute : null,
                    $depth !== null ? $this->getDepthValue() : null,
                    $depth,
                ),
            )->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Deletes the owner together with all its descendants.
     *
     * Runs in its own transaction regardless of the model `transactions()` configuration, acquires the table writer
     * lock, reloads the owner coordinates before deleting, and rolls back and restores the owner on failure.
     *
     * @throws Throwable if a lifecycle handler or database operation fails.
     *
     * @return bool|int Number of deleted rows, or `false` if {@see ActiveRecord::beforeDelete()} cancels the deletion.
     */
    public function deleteWithChildren(): bool|int
    {
        return $this->runOperation(function (): bool|int {
            $this->operation = self::OPERATION_DELETE_WITH_CHILDREN;

            return $this->deleteWithChildrenInternal();
        });
    }

    /**
     * Returns the {@see ActiveRecord} lifecycle event handlers of the behavior.
     *
     * @return array Handler method names indexed by event name.
     *
     * @return string[] The event handlers of the behavior.
     */
    public function events(): array
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeInsert',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
            ActiveRecord::EVENT_AFTER_REFRESH => 'afterRefresh',
        ];
    }

    /**
     * Places the owner as the next sibling of the target node, inserting a new owner or moving an existing subtree.
     *
     * Runs in its own transaction, acquires the table writer lock, rolls back and restores the owner on failure, and
     * reloads the coordinates of the owner and the target on success.
     *
     * @param ActiveRecord $node Node that becomes the previous sibling of the owner.
     * @param bool $runValidation Whether to validate the owner before saving.
     * @param list<string>|null $attributes Attributes to save, or `null` to save all attributes.
     *
     * @throws Throwable if a lifecycle handler or database operation fails.
     *
     * @return bool Whether the owner was saved.
     */
    public function insertAfter(ActiveRecord $node, bool $runValidation = true, array|null $attributes = null): bool
    {
        return $this->executeOperation($node, self::OPERATION_INSERT_AFTER, $runValidation, $attributes);
    }

    /**
     * Places the owner as the previous sibling of the target node, inserting a new owner or moving an existing subtree.
     *
     * Runs in its own transaction, acquires the table writer lock, rolls back and restores the owner on failure, and
     * reloads the coordinates of the owner and the target on success.
     *
     * @param ActiveRecord $node Node that becomes the next sibling of the owner.
     * @param bool $runValidation Whether to validate the owner before saving.
     * @param list<string>|null $attributes Attributes to save, or `null` to save all attributes.
     *
     * @throws Throwable if a lifecycle handler or database operation fails.
     *
     * @return bool Whether the owner was saved.
     */
    public function insertBefore(ActiveRecord $node, bool $runValidation = true, array|null $attributes = null): bool
    {
        return $this->executeOperation($node, self::OPERATION_INSERT_BEFORE, $runValidation, $attributes);
    }

    /**
     * Clears the cached coordinates and discards the pending operation and target node.
     */
    public function invalidateCache(): void
    {
        $this->afterRefresh();

        $this->node = null;
        $this->operation = null;
    }

    /**
     * Returns whether the owner is a descendant of the given node within the same tree.
     *
     * @param ActiveRecord $node Candidate ancestor of the owner.
     *
     * @return bool Whether the owner lies strictly inside the boundaries of the node and shares its tree identifier.
     */
    public function isChildOf(ActiveRecord $node): bool
    {
        $nodeLeft = $node->getAttribute($this->leftAttribute);
        $nodeRight = $node->getAttribute($this->rightAttribute);

        if ($this->getLeftValue() <= $nodeLeft || $this->getRightValue() >= $nodeRight) {
            return false;
        }

        if ($this->treeAttribute !== false) {
            return $this->getTreeValue($this->getOwner()) === $this->getTreeValue($node);
        }

        return true;
    }

    /**
     * Returns whether the owner has no descendants.
     *
     * @return bool Whether the right value of the owner exceeds its left value by exactly `1`.
     */
    public function isLeaf(): bool
    {
        return ($this->getRightValue() - $this->getLeftValue()) === 1;
    }

    /**
     * Returns whether the owner is a root node.
     *
     * @return bool Whether the left value of the owner is `1`.
     */
    public function isRoot(): bool
    {
        return $this->getOwner()->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * Returns a query for the leaf descendants of the owner, ordered by left value.
     *
     * @return ActiveQuery<T> Query for the descendants of the owner that have no children.
     */
    public function leaves(): ActiveQuery
    {
        return $this->getOwner()::find()
            ->andWhere(
                QueryConditionBuilder::createLeavesCondition(
                    $this->leftAttribute,
                    $this->rightAttribute,
                    $this->treeAttribute,
                    $this->getTreeValue($this->getOwner()),
                    $this->getLeftValue(),
                    $this->getRightValue(),
                ),
            )
            ->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Makes the owner a root, starting a new tree for a new owner or promoting an existing subtree to a new tree.
     *
     * Runs in its own transaction, acquires the table writer lock, rolls back and restores the owner on failure, and
     * reloads the owner coordinates on success. Promoting an existing node requires {@see $treeAttribute}.
     *
     * @param bool $runValidation Whether to validate the owner before saving.
     * @param list<string>|null $attributes Attributes to save, or `null` to save all attributes.
     *
     * @throws Throwable if a lifecycle handler or database operation fails.
     *
     * @return bool Whether the owner was saved.
     */
    public function makeRoot(bool $runValidation = true, array|null $attributes = null): bool
    {
        return $this->executeOperation(null, self::OPERATION_MAKE_ROOT, $runValidation, $attributes);
    }

    /**
     * Returns a query for the next sibling of the owner.
     *
     * @return ActiveQuery<T> Query for the node whose left value follows the right value of the owner; empty when there
     * is none.
     */
    public function next(): ActiveQuery
    {
        return $this->getOwner()::find()->andWhere(
            QueryConditionBuilder::createNextSiblingCondition(
                $this->leftAttribute,
                $this->getRightValue(),
                $this->treeAttribute,
                $this->getTreeValue($this->getOwner()),
            ),
        );
    }

    /**
     * Returns a query for the ancestors of the owner, ordered by left value and optionally limited in depth.
     *
     * @param int|null $depth Maximum number of levels above the owner, or `null` for all ancestors.
     *
     * @return ActiveQuery<T> Query for the ancestors of the owner.
     */
    public function parents(int|null $depth = null): ActiveQuery
    {
        return $this->getOwner()::find()
            ->andWhere(
                QueryConditionBuilder::createParentsCondition(
                    $this->leftAttribute,
                    $this->getLeftValue(),
                    $this->rightAttribute,
                    $this->getRightValue(),
                    $this->treeAttribute,
                    $this->getTreeValue($this->getOwner()),
                    $depth !== null ? $this->depthAttribute : null,
                    $depth !== null ? $this->getDepthValue() : null,
                    $depth,
                ),
            )
            ->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Prepends the owner as the first child of the target node, inserting a new owner or moving an existing subtree.
     *
     * Runs in its own transaction, acquires the table writer lock, rolls back and restores the owner on failure, and
     * reloads the coordinates of the owner and the target on success.
     *
     * @param ActiveRecord $node Node that becomes the parent of the owner.
     * @param bool $runValidation Whether to validate the owner before saving.
     * @param list<string>|null $attributes Attributes to save, or `null` to save all attributes.
     *
     * @throws Throwable if a lifecycle handler or database operation fails.
     *
     * @return bool Whether the owner was saved.
     */
    public function prependTo(ActiveRecord $node, bool $runValidation = true, array|null $attributes = null): bool
    {
        return $this->executeOperation($node, self::OPERATION_PREPEND_TO, $runValidation, $attributes);
    }

    /**
     * Returns a query for the previous sibling of the owner.
     *
     * @return ActiveQuery<T> Query for the node whose right value precedes the left value of the owner; empty when
     * there is none.
     */
    public function prev(): ActiveQuery
    {
        return $this->getOwner()::find()->andWhere(
            QueryConditionBuilder::createPrevSiblingCondition(
                $this->rightAttribute,
                $this->getLeftValue(),
                $this->treeAttribute,
                $this->getTreeValue($this->getOwner()),
            ),
        );
    }

    /**
     * Assigns the coordinates of a new child or sibling at the given position and opens space for it in the tree.
     *
     * @param int $value Left value of the new node.
     * @param int $depth Depth offset relative to the target node: `0` for a sibling, `1` for a child.
     *
     * @throws Exception if a sibling of a root node is requested.
     */
    protected function beforeInsertNode(int $value, int $depth): void
    {
        if ($depth === 0 && $this->node?->isRoot() === true) {
            throw new Exception(
                Message::CANNOT_CREATE_NODE_TARGET_IS_ROOT->getMessage(),
            );
        }

        $this->getOwner()->setAttribute($this->leftAttribute, $value);
        $this->getOwner()->setAttribute($this->rightAttribute, $value + 1);

        $nodeDepthValue = $this->node?->getAttribute($this->depthAttribute) ?? 0;

        $this->getOwner()->setAttribute($this->depthAttribute, $nodeDepthValue + $depth);

        if ($this->treeAttribute !== false && $this->node !== null) {
            $this->getOwner()->setAttribute($this->treeAttribute, $this->node->getAttribute($this->treeAttribute));
        }

        $this->shiftLeftRightAttribute(
            $value,
            2,
        );
    }

    /**
     * Assigns root coordinates to a new owner.
     *
     * @throws Exception if {@see $treeAttribute} is `false` and the table already contains a node.
     */
    protected function beforeInsertRootNode(): void
    {
        if ($this->treeAttribute === false && TreeLock::exists($this->getOwner()::find())) {
            throw new Exception(Message::CANNOT_CREATE_MULTIPLE_ROOTS->getMessage());
        }

        $this->getOwner()->setAttribute($this->leftAttribute, 1);
        $this->getOwner()->setAttribute($this->rightAttribute, 2);
        $this->getOwner()->setAttribute($this->depthAttribute, 0);
    }

    /**
     * Moves the owner subtree to the position described by the context, within its tree or into another tree.
     *
     * @param NodeContext $context Target position and depth offset of the move.
     */
    protected function moveNode(NodeContext $context): void
    {
        $currentTreeValue = $this->getTreeValue($this->getOwner());
        $targetTreeValue = $context->getTargetTreeValue($this->treeAttribute);

        if ($this->treeAttribute === false || $currentTreeValue === $targetTreeValue) {
            $this->executeSameTreeMove(
                $context,
                $currentTreeValue,
            );
        } else {
            $this->executeCrossTreeMove(
                $context,
                $this->treeAttribute,
                $currentTreeValue,
                $targetTreeValue,
            );
        }
    }

    /**
     * Promotes the owner subtree to a new tree identified by the owner primary key and closes the gap it leaves.
     *
     * @param mixed $treeValue Identifier of the tree that currently contains the subtree.
     */
    protected function moveNodeAsRoot(mixed $treeValue): void
    {
        $this->moveSubtreeToTargetTree(
            $treeValue,
            $this->getOwner()->getPrimaryKey(),
            -$this->getDepthValue(),
            $this->getLeftValue(),
            1 - $this->getLeftValue(),
            $this->getRightValue(),
        );
        $this->shiftLeftRightAttribute(
            $this->getRightValue(),
            $this->getLeftValue() - $this->getRightValue() - 1,
        );
    }

    /**
     * Adds the delta to every left and right value greater than or equal to the given value within the owner tree.
     *
     * @param int $value Lowest boundary value to shift, inclusive.
     * @param int $delta Signed offset to add; a negative value closes a gap.
     */
    protected function shiftLeftRightAttribute(int $value, int $delta): void
    {
        foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
            $this->getOwner()::updateAll(
                QueryConditionBuilder::createOffsetUpdates(
                    $this->getDb(),
                    [
                        $attribute => $delta,
                    ],
                ),
                QueryConditionBuilder::createShiftCondition(
                    $attribute,
                    $value,
                    $this->treeAttribute,
                    $this->getTreeValue($this->getOwner()),
                ),
            );
        }
    }

    /**
     * Assigns the coordinates of a new owner from the insertion context.
     *
     * @param NodeContext $context Target position and depth offset of the insertion.
     *
     * @throws Exception if a sibling of a root node is requested.
     */
    private function beforeInsertNodeWithContext(NodeContext $context): void
    {
        $this->beforeInsertNode($context->targetPositionValue, $context->depthLevelDelta);
    }

    /**
     * Returns the movement context for the given operation and target node.
     *
     * @param ActiveRecord $targetNode Node the move is relative to.
     * @param string|null $operation Pending operation identifier.
     *
     * @throws RuntimeException if the operation does not move a node relative to a target.
     *
     * @return NodeContext Target position and depth offset of the move.
     */
    private function createMoveContext(ActiveRecord $targetNode, string|null $operation): NodeContext
    {
        return match ($operation) {
            self::OPERATION_APPEND_TO => NodeContext::forAppendTo($targetNode, $this->rightAttribute),
            self::OPERATION_INSERT_AFTER => NodeContext::forInsertAfter($targetNode, $this->rightAttribute),
            self::OPERATION_INSERT_BEFORE => NodeContext::forInsertBefore($targetNode, $this->leftAttribute),
            self::OPERATION_PREPEND_TO => NodeContext::forPrependTo($targetNode, $this->leftAttribute),
            default => throw new RuntimeException(Message::UNSUPPORTED_OPERATION->getMessage((string) $operation)),
        };
    }

    /**
     * Deletes the owner subtree in one statement, running the owner delete lifecycle hooks around it.
     *
     * @return false|int Number of deleted rows, or `false` if {@see ActiveRecord::beforeDelete()} cancels the deletion.
     */
    private function deleteWithChildrenInternal(): bool|int
    {
        if ($this->getOwner()->beforeDelete() === false) {
            return false;
        }

        $result = $this->getOwner()::deleteAll(
            QueryConditionBuilder::createRangeCondition(
                $this->leftAttribute,
                $this->getLeftValue(),
                $this->rightAttribute,
                $this->getRightValue(),
                $this->treeAttribute,
                $this->getTreeValue($this->getOwner()),
            ),
        );

        $this->getOwner()->setOldAttributes(null);
        $this->getOwner()->afterDelete();

        return $result;
    }

    /**
     * Moves the owner subtree into another tree at the context position and closes the gap in the source tree.
     *
     * @param NodeContext $context Target position and depth offset of the move.
     * @param string $treeAttribute Name of the tree attribute.
     * @param mixed $currentTreeValue Identifier of the source tree.
     * @param mixed $targetTreeValue Identifier of the destination tree.
     */
    private function executeCrossTreeMove(
        NodeContext $context,
        string $treeAttribute,
        mixed $currentTreeValue,
        mixed $targetTreeValue,
    ): void {
        $targetDepthValue = $context->getTargetDepth($this->depthAttribute);
        $depthOffset = $targetDepthValue - $this->getDepthValue() + $context->depthLevelDelta;

        foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
            $this->getOwner()::updateAll(
                QueryConditionBuilder::createOffsetUpdates(
                    $this->getDb(),
                    [
                        $attribute => $this->getRightValue() - $this->getLeftValue() + 1,
                    ],
                ),
                QueryConditionBuilder::createCrossTreeMoveCondition(
                    $attribute,
                    $context->targetPositionValue,
                    $treeAttribute,
                    $targetTreeValue,
                ),
            );
        }

        $this->moveSubtreeToTargetTree(
            $currentTreeValue,
            $targetTreeValue,
            $depthOffset,
            $this->getLeftValue(),
            $context->targetPositionValue - $this->getLeftValue(),
            $this->getRightValue(),
        );
        $this->shiftLeftRightAttribute(
            $this->getRightValue(),
            $this->getLeftValue() - $this->getRightValue() - 1,
        );
    }

    /**
     * Saves the owner with the given pending operation through {@see runOperation()} and reloads affected coordinates.
     *
     * @param ActiveRecord|null $targetNode Node the operation is relative to, or `null` for {@see OPERATION_MAKE_ROOT}.
     * @param string $operation Operation identifier, one of the `OPERATION_*` constants.
     * @param bool $runValidation Whether to validate the owner before saving.
     * @param list<string>|null $attributes Attributes to save, or `null` to save all attributes.
     *
     * @throws Exception if the saved node cannot be reloaded.
     *
     * @return bool Whether the owner was saved.
     */
    private function executeOperation(
        ActiveRecord|null $targetNode,
        string $operation,
        bool $runValidation,
        array|null $attributes = null,
    ): bool {
        return $this->runOperation(function () use ($targetNode, $operation, $runValidation, $attributes): bool {
            $this->operation = $operation;
            $this->node = $targetNode;

            $isNewRecord = $this->getOwner()->getIsNewRecord();
            $lock = $this->getOwner()->optimisticLock();

            if ($attributes !== null && $isNewRecord) {
                $attributes = [...$attributes, ...$this->structuralAttributes()];
            } elseif ($attributes !== null && $lock !== null) {
                $attributes[] = $lock;
            }

            $result = $this->getOwner()->save($runValidation, $attributes);

            if ($result) {
                if ($isNewRecord) {
                    $owner = $this->getOwner();

                    $pending = array_intersect_key($owner->getAttributes(), $owner->getDirtyAttributes());

                    foreach ($this->structuralAttributes() as $attribute) {
                        unset($pending[$attribute]);
                    }

                    if ($owner->refresh() === false) {
                        throw new StaleObjectException(Message::NODE_NOT_FOUND->getMessage());
                    }

                    $owner->setAttributes($pending, false);
                } else {
                    $this->refreshStructure($this->getOwner());
                }

                if ($targetNode !== null) {
                    $this->refreshStructure($targetNode, false, false);
                }
            }

            return $result;
        });
    }

    /**
     * Moves the owner subtree to the context position within its current tree.
     *
     * @param NodeContext $context Target position and depth offset of the move.
     * @param mixed $currentTreeValue Identifier of the tree that contains the subtree.
     */
    private function executeSameTreeMove(NodeContext $context, mixed $currentTreeValue): void
    {
        $subtreeSize = $this->getRightValue() - $this->getLeftValue() + 1;
        $targetDepthValue = $context->getTargetDepth($this->depthAttribute);
        $depthOffset = $targetDepthValue - $this->getDepthValue() + $context->depthLevelDelta;

        $this->shiftLeftRightAttribute(
            $context->targetPositionValue,
            $subtreeSize,
        );

        $adjustedLeftValue = $this->getLeftValue();
        $adjustedRightValue = $this->getRightValue();

        // We use '>=' for consistency, though equality is impossible due to the nature of the algorithm
        // @infection-ignore-all
        if ($adjustedLeftValue >= $context->targetPositionValue) {
            $adjustedLeftValue += $subtreeSize;
            $adjustedRightValue += $subtreeSize;
        }

        $this->getOwner()::updateAll(
            QueryConditionBuilder::createOffsetUpdates(
                $this->getDb(),
                [
                    $this->depthAttribute => $depthOffset,
                ],
            ),
            QueryConditionBuilder::createRangeCondition(
                $this->leftAttribute,
                $adjustedLeftValue,
                $this->rightAttribute,
                $adjustedRightValue,
                $this->treeAttribute,
                $currentTreeValue,
            ),
        );

        foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
            $this->getOwner()::updateAll(
                QueryConditionBuilder::createOffsetUpdates(
                    $this->getDb(),
                    [
                        $attribute => $context->targetPositionValue - $adjustedLeftValue,
                    ],
                ),
                QueryConditionBuilder::createRangeCondition(
                    $attribute,
                    $adjustedLeftValue,
                    $attribute,
                    $adjustedRightValue,
                    $this->treeAttribute,
                    $currentTreeValue,
                ),
            );
        }

        $this->shiftLeftRightAttribute(
            $adjustedRightValue,
            -$subtreeSize,
        );
    }

    /**
     * Returns the database connection of the owner model, resolving it once.
     *
     * @return Connection Connection of the owner model.
     */
    private function getDb(): Connection
    {
        return $this->db ??= $this->getOwner()::getDb();
    }

    /**
     * Returns the depth of the owner, caching it until the next refresh.
     *
     * @return int Depth of the owner.
     */
    private function getDepthValue(): int
    {
        return $this->depthValue ??= $this->getOwner()->getAttribute($this->depthAttribute);
    }

    /**
     * Returns the left value of the owner, caching it until the next refresh.
     *
     * @return int Left value of the owner.
     */
    private function getLeftValue(): int
    {
        return $this->leftValue ??= $this->getOwner()->getAttribute($this->leftAttribute);
    }

    /**
     * Returns the model the behavior is attached to.
     *
     * @throws LogicException if the behavior is not attached.
     *
     * @return T Owner model.
     */
    private function getOwner(): ActiveRecord
    {
        if ($this->owner === null) {
            throw new LogicException(
                Message::BEHAVIOR_NOT_ATTACHED->getMessage(),
            );
        }

        return $this->owner;
    }

    /**
     * Returns the right value of the owner, caching it until the next refresh.
     *
     * @return int Right value of the owner.
     */
    private function getRightValue(): int
    {
        return $this->rightValue ??= $this->getOwner()->getAttribute($this->rightAttribute);
    }

    /**
     * Returns the tree identifier of the given record.
     *
     * @param ActiveRecord|null $activeRecord Record to read, or `null`.
     *
     * @return mixed Tree identifier, or `null` when {@see $treeAttribute} is `false` or no record is given.
     */
    private function getTreeValue(ActiveRecord|null $activeRecord): mixed
    {
        return $activeRecord !== null && $this->treeAttribute !== false
            ? $activeRecord->getAttribute($this->treeAttribute)
            : null;
    }

    /**
     * Returns a scalar optimistic lock version as a `string` so integer and numeric `string` versions compare equal.
     *
     * @return mixed Optimistic lock version as a `string` if scalar, otherwise the original value.
     */
    private static function lockVersion(mixed $value): mixed
    {
        return is_scalar($value) ? (string) $value : $value;
    }

    /**
     * Applies depth and position offsets to a subtree and assigns it the target tree identifier in one statement.
     *
     * @param mixed $currentTreeValue Identifier of the tree that currently contains the subtree.
     * @param mixed $targetTreeValue Identifier of the tree that receives the subtree.
     * @param int $depth Depth offset to apply to every node of the subtree.
     * @param int $leftValue Left value of the subtree root.
     * @param int $positionOffset Offset to add to the left and right values of the subtree.
     * @param int $rightValue Right value of the subtree root.
     *
     * @throws LogicException if {@see $treeAttribute} is `false`.
     */
    private function moveSubtreeToTargetTree(
        mixed $currentTreeValue,
        mixed $targetTreeValue,
        int $depth,
        int $leftValue,
        int $positionOffset,
        int $rightValue,
    ): void {
        $attributes = QueryConditionBuilder::createOffsetUpdates(
            $this->getDb(),
            [
                $this->depthAttribute => $depth,
                $this->leftAttribute => $positionOffset,
                $this->rightAttribute => $positionOffset,
            ],
        );

        if ($this->treeAttribute === false) {
            throw new LogicException(
                Message::TREE_ATTRIBUTE_REQUIRED->getMessage(),
            );
        }

        $attributes += [$this->treeAttribute => $targetTreeValue];

        $this->getOwner()::updateAll(
            $attributes,
            QueryConditionBuilder::createSubtreeMoveCondition(
                $this->leftAttribute,
                $leftValue,
                $this->rightAttribute,
                $rightValue,
                $this->treeAttribute,
                $currentTreeValue,
            ),
        );
    }

    /**
     * Reloads managed coordinates without discarding unsaved application attributes.
     *
     * @param ActiveRecord $node Node whose coordinates are reloaded from the primary database.
     * @param bool $checkLock Whether to reject a node whose optimistic lock version has changed.
     * @param bool $required Whether a missing row is an error rather than a silent no-op.
     *
     * @throws StaleObjectException if the node no longer exists and `$required` is `true`, or if `$checkLock` is `true`
     * and the optimistic lock version has changed.
     */
    private function refreshStructure(ActiveRecord $node, bool $checkLock = false, bool $required = true): void
    {
        $query = $node::find();
        $tableName = key($query->getTablesUsedInFrom());

        $condition = [];

        foreach ($node::primaryKey() as $attribute) {
            $condition["{$tableName}.{$attribute}"] = $node->getOldAttribute($attribute);
        }

        $record = $node::getDb()->useMaster(
            static fn(): ActiveRecord|null => TreeLock::one($query->where($condition)),
        );

        if ($record instanceof ActiveRecord === false) {
            if ($required === false) {
                return;
            }

            throw new StaleObjectException(
                Message::NODE_NOT_FOUND->getMessage(),
            );
        }

        $lock = $node->optimisticLock();

        // Request data assigns versions as numeric strings while database reads return integers.
        if (
            $checkLock
            && $lock !== null
            && self::lockVersion($record->getAttribute($lock)) !== self::lockVersion($node->getAttribute($lock))
        ) {
            throw new StaleObjectException(
                Message::NODE_MODIFIED->getMessage(),
            );
        }

        foreach ($this->structuralAttributes() as $attribute) {
            $value = $record->getAttribute($attribute);
            $node->setAttribute($attribute, $value);
            $node->setOldAttribute($attribute, $value);
        }

        foreach ($node->getBehaviors() as $behavior) {
            if ($behavior instanceof self) {
                $behavior->afterRefresh();
            }
        }
    }

    /**
     * Verifies that a target belongs to the same nested set storage before reading its coordinates.
     *
     * @throws Exception if the target uses another database connection or table.
     * @throws StaleObjectException if the target no longer exists.
     */
    private function refreshTarget(ActiveRecord $node): void
    {
        if ($node::getDb() !== $this->getDb() || $node::tableName() !== $this->getOwner()::tableName()) {
            throw new Exception(
                Message::TARGET_STORAGE_MISMATCH->getMessage(),
            );
        }

        $this->refreshStructure($node);
    }

    /**
     * Rolls back failed operations and restores the owner so it can be retried.
     *
     * Runs the callback in its own transaction after acquiring the table writer lock. A `false` result rolls back the
     * transaction, an exception is rethrown after the rollback, and the cached state is cleared in every case.
     *
     * @template TResult of bool|int
     *
     * @param callable(): TResult $callback Structural operation to run under the writer lock.
     *
     * @throws Throwable if acquiring the lock, the callback, or the rollback fails.
     *
     * @return TResult Result of the callback.
     */
    private function runOperation(callable $callback): bool|int
    {
        $owner = $this->getOwner();
        $oldAttributes = $owner->getIsNewRecord() ? null : $owner->getOldAttributes();
        $attributes = $owner->getAttributes();

        if ($oldAttributes === null) {
            $attributes = array_intersect_key($attributes, $owner->getDirtyAttributes());
        }
        $transaction = $this->getDb()->beginTransaction();

        $succeeded = false;

        try {
            TreeLock::acquire($owner, $this->lockTimeout);

            $result = $callback();

            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
                $succeeded = true;
            }

            return $result;
        } catch (Throwable $exception) {
            TreeLock::rollBack($this->getDb(), $transaction, $exception);

            throw $exception;
        } finally {
            if ($succeeded === false) {
                foreach ($owner->getAttributes() as $attribute => $_value) {
                    if (array_key_exists($attribute, $attributes) === false) {
                        $owner->__unset($attribute);
                    }
                }

                $owner->setAttributes($attributes, false);
                $owner->setOldAttributes($oldAttributes);
            }

            $this->invalidateCache();
        }
    }

    /**
     * Returns the names of the attributes managed by the behavior.
     *
     * @return list<string> Attributes managed by this behavior.
     */
    private function structuralAttributes(): array
    {
        $attributes = [$this->leftAttribute, $this->rightAttribute, $this->depthAttribute];

        if ($this->treeAttribute !== false) {
            $attributes[] = $this->treeAttribute;
        }

        return $attributes;
    }
}
