<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use yii\db\{ActiveRecord, Exception};

/**
 * Centralized manager for nested sets database update operations.
 *
 * Provides a unified interface for all database update operations in the nested sets behavior, eliminating code
 * duplication and ensuring consistent update patterns across all operations.
 *
 * This class orchestrates the QueryConditionBuilder and UpdateExpressionBuilder to perform complex update operations
 * while maintaining the integrity of the nested set structure.
 *
 * Key features.
 * - Centralized update operation management.
 * - Consistent condition and expression building.
 * - Optimized bulk update operations.
 * - Transaction-aware update coordination.
 * - Type-safe database operations.
 *
 * @phpstan-template T of ActiveRecord
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class NestedSetsUpdateManager
{
    /**
     * Builder for nested sets query conditions.
     */
    private readonly QueryConditionBuilder $conditionBuilder;

    /**
     * Left boundary value of the nested set node.
     */
    private int|null $leftValue = null;

    /**
     * Right boundary value of the nested set node.
     */
    private int|null $rightValue = null;

    /**
     * Tree value for multi-tree support, or null if not applicable.
     *
     * This is used to scope updates to a specific tree when the behavior supports multiple trees.
     */
    private mixed $treeValue = null;

    /**
     * Builder for nested sets update expressions and operations.
     */
    private readonly UpdateExpressionBuilder $expressionBuilder;

    /**
     * @phpstan-param ActiveRecord<T> $owner
     * @phpstan-param class-string<T> $modelClass
     * @phpstan-param 'lft' $leftAttribute
     * @phpstan-param 'rgt' $rightAttribute
     */
    public function __construct(
        private readonly ActiveRecord $owner,
        private readonly string $modelClass,
        private readonly string $leftAttribute,
        private readonly string $rightAttribute,
        private readonly string $depthAttribute,
        private readonly string|false $treeAttribute,
    ) {
        $this->conditionBuilder = new QueryConditionBuilder(
            $leftAttribute,
            $rightAttribute,
            $treeAttribute,
        );
        $this->expressionBuilder = new UpdateExpressionBuilder(
            $leftAttribute,
            $rightAttribute,
            $depthAttribute,
            $treeAttribute,
            $modelClass::getDb(),
        );
    }

    /**
     * Performs bulk update for shifting left/right attributes.
     *
     * @param int $fromValue Starting value for the shift operation.
     * @param int $offset Offset to apply (can be negative).
     * @param mixed $treeValue Tree attribute value for scoping.
     */
    public function shiftBoundaryAttributes(int $fromValue, int $offset, mixed $treeValue): void
    {
        foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
            $this->modelClass::updateAll(
                $this->expressionBuilder->createSingleAttributeUpdate($attribute, $offset),
                $this->conditionBuilder->createCrossTreeCondition($attribute, $fromValue, $treeValue),
            );
        }
    }

    /**
     * Moves a subtree to a target tree with position and depth adjustments.
     *
     * @param int $leftValue Left boundary of the subtree.
     * @param int $rightValue Right boundary of the subtree.
     * @param int $positionOffset Position offset for movement.
     * @param int $depthOffset Depth offset for movement.
     * @param mixed $currentTreeValue Current tree value of the subtree.
     * @param mixed $targetTreeValue Target tree value.
     */
    public function moveSubtreeToTargetTree(
        int $leftValue,
        int $rightValue,
        int $positionOffset,
        int $depthOffset,
        mixed $currentTreeValue,
        mixed $targetTreeValue,
    ): void {
        $this->modelClass::updateAll(
            $this->expressionBuilder->createSubtreeMovementAttributes($positionOffset, $depthOffset, $targetTreeValue),
            $this->conditionBuilder->createSubtreeCondition($leftValue, $rightValue, $currentTreeValue),
        );
    }

    /**
     * Updates depth for nodes within a specific range.
     *
     * @param int $leftValue Left boundary of the range.
     * @param int $rightValue Right boundary of the range.
     * @param int $depthOffset Depth offset to apply.
     * @param mixed $treeValue Tree attribute value for scoping.
     */
    public function updateDepthInRange(int $leftValue, int $rightValue, int $depthOffset, mixed $treeValue): void
    {
        $this->modelClass::updateAll(
            $this->expressionBuilder->createDepthUpdateAttributes($depthOffset),
            $this->conditionBuilder->createSubtreeCondition($leftValue, $rightValue, $treeValue),
        );
    }

    /**
     * Performs boundary adjustment for cross-tree movements.
     *
     * @param string $attribute Attribute to adjust ('lft' or 'rgt').
     * @param int $fromValue Starting value for adjustment.
     * @param int $adjustment Adjustment offset.
     * @param mixed $treeValue Tree attribute value for scoping.
     */
    public function adjustAttributeFromValue(string $attribute, int $fromValue, int $adjustment, mixed $treeValue): void
    {
        $this->modelClass::updateAll(
            $this->expressionBuilder->createSingleAttributeUpdate($attribute, $adjustment),
            $this->conditionBuilder->createCrossTreeCondition($attribute, $fromValue, $treeValue),
        );
    }

    /**
     * Performs same-tree node movement with position adjustment.
     *
     * @param int $leftValue Left boundary of moving subtree.
     * @param int $rightValue Right boundary of moving subtree.
     * @param int $depthOffset Depth adjustment.
     * @param int $positionOffset Position adjustment.
     * @param mixed $treeValue Tree attribute value for scoping.
     */
    public function moveSameTreeNode(
        int $leftValue,
        int $rightValue,
        int $depthOffset,
        int $positionOffset,
        mixed $treeValue,
    ): void {
        $this->updateDepthInRange($leftValue, $rightValue, $depthOffset, $treeValue);

        foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
            $this->modelClass::updateAll(
                $this->expressionBuilder->createSingleAttributeUpdate($attribute, $positionOffset),
                $this->conditionBuilder->createSubtreeCondition($leftValue, $rightValue, $treeValue),
            );
        }
    }

    /**
     * Updates subtree attributes for deletion cleanup.
     */
    public function updateSubtreeForDeletion(string|null $operation): void
    {
        $delta = -2;

        if ($operation === NestedSetsBehavior::OPERATION_DELETE_WITH_CHILDREN || $this->owner->isLeaf()) {
            $delta = $this->getLeftValue() - $this->getRightValue() - 1;
        }

        $this->modelClass::updateAll(
            [
                $this->leftAttribute => $this->expressionBuilder->createOffsetExpression($this->leftAttribute, -1),
                $this->rightAttribute => $this->expressionBuilder->createOffsetExpression($this->rightAttribute, -1),
                $this->depthAttribute => $this->expressionBuilder->createOffsetExpression($this->depthAttribute, -1),
            ],
            $this->conditionBuilder->createSubtreeCondition(
                $this->getLeftValue(),
                $this->getRightValue(),
                $this->getTreeValue(),
            ),
        );
        $this->shiftLeftRightAttribute($this->getRightValue(), $delta);
    }

    /**
     * Updates the tree attribute for a root node after insertion.
     *
     * Sets the tree attribute of the owner node to its primary key value and updates the corresponding record in the
     * database, ensuring the root node is correctly identified in multi-tree scenarios.
     *
     * This operation is essential when creating root nodes in a multi-tree nested set structure where each tree is
     * identified by a unique tree attribute value.
     *
     * @throws Exception if the model class does not have a primary key defined.
     */
    public function updateTreeAttributeForRoot(): void
    {
        if ($this->treeAttribute === false) {
            return;
        }

        $primaryKey = $this->owner::primaryKey();

        if (isset($primaryKey[0]) === false) {
            throw new Exception('"' . $this->modelClass . '" must have a primary key.');
        }

        $this->owner->setAttribute($this->treeAttribute, $this->owner->getPrimaryKey());
        $this->owner::updateAll(
            [
                $this->treeAttribute => $this->getTreeValue(),
            ],
            [
                $primaryKey[0] => $this->getTreeValue(),
            ],
        );
    }

    public function getLeftValue(): int
    {
        if ($this->leftValue === null) {
            $this->leftValue = $this->owner->getAttribute($this->leftAttribute);
        }

        return  $this->leftValue;
    }

    public function getRightValue(): int
    {
        if ($this->rightValue === null) {
            $this->rightValue = $this->owner->getAttribute($this->rightAttribute);
        }

        return  $this->rightValue;
    }

    private function getTreeValue(): mixed
    {
        if ($this->treeAttribute === false) {
            return null;
        }

        if ($this->treeValue === null) {
            $this->treeValue = $this->owner->getAttribute($this->treeAttribute);
        }

        return $this->treeValue;
    }

    /**
     * Shifts left and right attribute values for nodes after a structural change in the nested set tree.
     *
     * Updates the left and right boundary attributes of all nodes whose attribute value is greater than or equal to the
     * specified value, applying the given delta.
     *
     * This operation is essential for maintaining the integrity of the nested set structure after insertions,
     * deletions, or moves, ensuring that all affected nodes are correctly renumbered.
     *
     * The method applies the tree attribute condition if multi-tree support is enabled, restricting the update to nodes
     * within the same tree.
     *
     * @param int $value Attribute value from which to start shifting (inclusive).
     * @param int $delta Amount to add to the attribute value for affected nodes (can be negative).
     */
    public function shiftLeftRightAttribute(int $value, int $delta): void
    {
        $this->shiftBoundaryAttributes($value, $delta, $this->getTreeValue());
    }
}
