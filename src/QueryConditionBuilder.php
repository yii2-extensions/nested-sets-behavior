<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use yii\db\{Connection, Expression};

use function sprintf;

/**
 * Utility class for building query conditions for nested sets operations.
 *
 * Provides static methods to create standardized condition arrays for common nested sets queries such as finding
 * descendants, ancestors, siblings, or filtering by tree.
 *
 * These methods ensure consistent condition structure and reduce code duplication across the nested sets behavior.
 *
 * This class focuses on creating query conditions without executing them, making it useful for both the behavior
 * implementation and external queries that need to work with nested sets.
 *
 * Key features:
 * - Creates standardized condition arrays for nested sets queries.
 * - Enables consistent tree filtering across multiple operations.
 * - Handles range conditions for subtree operations.
 * - Supports specific node relationship queries (children, parents, siblings).
 * - Provides leaf node and level-based filtering conditions.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class QueryConditionBuilder
{
    /**
     * Creates a condition array for finding children of a node with specific left and right values.
     *
     * This method builds a condition that identifies all nodes that are direct descendants of a node with the specified
     * boundaries.
     *
     * It requires the left attribute to be greater than the parent's left value and the right attribute to be less than
     * the parent's right value.
     *
     * Optionally limits the depth of children if the depth parameter is provided.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $leftValue Parent node left value.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $rightValue Parent node right value.
     * @param false|string $treeAttribute Name of tree attribute or `false` if disabled.
     * @param mixed $treeValue Tree value to filter by (ignored if treeAttribute is `false`).
     * @param string|null $depthAttribute Name of the depth attribute, or `null` if depth filtering is not needed.
     * @param int|null $parentDepth Parent node depth value, required if $maxRelativeDepth is provided.
     * @param int|null $maxRelativeDepth Maximum relative depth from parent, or `null` for all descendants.
     *
     * @return array Condition array for finding children of the specified node.
     *
     * Usage example:
     * ```php
     * // Find all children up to 2 levels deep
     * $condition = QueryConditionBuilder::createChildrenCondition(
     *     'lft', $node->lft, 'rgt', $node->rgt, 'tree', $node->tree, 'depth', $node->depth, 2,
     * );
     * $children = MyModel::find()->andWhere($condition)->all();
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createChildrenCondition(
        string $leftAttribute,
        int $leftValue,
        string $rightAttribute,
        int $rightValue,
        string|false $treeAttribute = false,
        mixed $treeValue = null,
        string|null $depthAttribute = null,
        int|null $parentDepth = null,
        int|null $maxRelativeDepth = null,
    ): array {
        $condition = [
            'and',
            ['>', $leftAttribute, $leftValue],
            ['<', $rightAttribute, $rightValue],
        ];

        if ($depthAttribute !== null && $parentDepth !== null && $maxRelativeDepth !== null) {
            $condition[] = ['<=', $depthAttribute, $parentDepth + $maxRelativeDepth];
        }

        if ($treeAttribute !== false) {
            $condition[] = [$treeAttribute => $treeValue];
        }

        return $condition;
    }

    /**
     * Creates a condition array for cross-tree movement operations.
     *
     * This method builds a condition for updating nodes when moving subtrees between different trees.
     *
     * @param string $attribute Name of the attribute to check (left or right).
     * @param int $value Minimum value for the attribute.
     * @param string $treeAttribute Name of tree attribute.
     * @param mixed $treeValue Tree value to filter by.
     *
     * @return array Condition array for cross-tree operations.
     *
     * Usage example:
     * ```php
     * $condition = QueryConditionBuilder::createCrossTreeMoveCondition('lft', 5, 'tree', 2);
     * MyModel::updateAll(['lft' => new Expression('lft + 10')], $condition);
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createCrossTreeMoveCondition(
        string $attribute,
        int $value,
        string $treeAttribute,
        mixed $treeValue,
    ): array {
        return [
            'and',
            ['>=', $attribute, $value],
            [$treeAttribute => $treeValue],
        ];
    }

    /**
     * Creates a condition array for finding leaf nodes (nodes without children).
     *
     * This method builds a condition that identifies all nodes where the right attribute is exactly one greater than
     * the left attribute, indicating that the node has no children.
     *
     * Optionally restricts the search to descendants of a specific node if parent boundaries are provided.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param false|string $treeAttribute Name of tree attribute or `false` if disabled.
     * @param mixed $treeValue Tree value to filter by (ignored if treeAttribute is `false`).
     * @param int|null $parentLeftValue Optional parent left value to restrict search to a subtree.
     * @param int|null $parentRightValue Optional parent right value to restrict search to a subtree.
     *
     * @return array Condition array for finding leaf nodes.
     *
     * Usage example:
     * ```php
     * // Find all leaf nodes in the tree
     * $condition = QueryConditionBuilder::createLeavesCondition('lft', 'rgt', 'tree', 1);
     * $leaves = MyModel::find()->andWhere($condition)->all();
     *
     * // Find leaf nodes only within a specific subtree
     * $condition = QueryConditionBuilder::createLeavesCondition('lft', 'rgt', 'tree', 1, $node->lft, $node->rgt);
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createLeavesCondition(
        string $leftAttribute,
        string $rightAttribute,
        string|false $treeAttribute = false,
        mixed $treeValue = null,
        int|null $parentLeftValue = null,
        int|null $parentRightValue = null,
    ): array {
        $condition = [
            'and',
            [$rightAttribute => new Expression("{{{$leftAttribute}}} + 1")],
            ['>', $leftAttribute, $parentLeftValue],
            ['<', $rightAttribute, $parentRightValue],
        ];

        if ($treeAttribute !== false) {
            $condition[] = [$treeAttribute => $treeValue];
        }

        return $condition;
    }

    /**
     * Creates a condition array for finding the next sibling of a node.
     *
     * This method builds a condition that identifies the node whose left attribute is exactly one greater than the
     * right attribute of the reference node.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $rightValue Reference node right value.
     * @param false|string $treeAttribute Name of tree attribute or `false` if disabled.
     * @param mixed $treeValue Tree value to filter by (ignored if treeAttribute is `false`).
     *
     * @return array Condition array for finding the next sibling.
     *
     * Usage example:
     * ```php
     * $condition = QueryConditionBuilder::createNextSiblingCondition('lft', $node->rgt, 'tree', $node->tree);
     * $nextSibling = MyModel::find()->andWhere($condition)->one();
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createNextSiblingCondition(
        string $leftAttribute,
        int $rightValue,
        string|false $treeAttribute = false,
        mixed $treeValue = null,
    ): array {
        $condition = [$leftAttribute => $rightValue + 1];

        if ($treeAttribute !== false) {
            $condition = ['and', $condition, [$treeAttribute => $treeValue]];
        }

        return $condition;
    }

    /**
     * Creates multiple SQL expressions for incrementing or decrementing attributes by specific offsets.
     *
     * Generates an array of attribute => Expression pairs for bulk update operations in nested sets tree restructuring.
     * This method simplifies creating multiple offset expressions in a single call.
     *
     * @param Connection $db Database connection for proper column quoting.
     * @param array $attributeOffsets Array of attribute => offset pairs.
     *
     * @return array Array of attribute => Expression pairs for updateAll operations.
     *
     * Usage example:
     * ```php
     * $updates = QueryConditionBuilder::createOffsetUpdates($db, [
     *     'depth' => -1,
     *     'lft' => 5,
     *     'rgt' => 5,
     * ]);
     * // Result: [
     * //     'depth' => Expression('`depth` - 1'),
     * //     'lft' => Expression('`lft` + 5'),
     * //     'rgt' => Expression('`rgt` + 5'),
     * // ]
     *
     * MyModel::updateAll($updates, $condition);
     * ```
     *
     * @phpstan-param array<string, int> $attributeOffsets
     *
     * @phpstan-return array<string, Expression>
     */
    public static function createOffsetUpdates(Connection $db, array $attributeOffsets): array
    {
        $updates = [];

        foreach ($attributeOffsets as $attribute => $offset) {
            $updates[$attribute] = self::createOffsetExpression($db, $attribute, $offset);
        }

        return $updates;
    }

    /**
     * Creates a condition array for finding parent nodes of a node with specific left and right values.
     *
     * This method builds a condition that identifies all nodes that are ancestors of a node with the specified
     * boundaries.
     *
     * It requires the left attribute to be less than the child's left value and the right attribute to be greater than
     * the child's right value.
     *
     * Optionally limits the depth of parents if the depth parameter is provided.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $leftValue Child node left value.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $rightValue Child node right value.
     * @param false|string $treeAttribute Name of tree attribute or `false` if disabled.
     * @param mixed $treeValue Tree value to filter by (ignored if treeAttribute is `false`).
     * @param string|null $depthAttribute Name of the depth attribute, or `null` if depth filtering is not needed.
     * @param int|null $childDepth Child node depth value, required if $maxRelativeDepth is provided.
     * @param int|null $maxRelativeDepth Maximum relative depth from child, or `null` for all ancestors.
     *
     * @return array Condition array for finding parents of the specified node.
     *
     * Usage example:
     * ```php
     * // Find direct parent and grandparent only
     * $condition = QueryConditionBuilder::createParentsCondition(
     *     'lft', $node->lft, 'rgt', $node->rgt, 'tree', $node->tree, 'depth', $node->depth, 2,
     * );
     * $parents = MyModel::find()->andWhere($condition)->all();
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createParentsCondition(
        string $leftAttribute,
        int $leftValue,
        string $rightAttribute,
        int $rightValue,
        string|false $treeAttribute = false,
        mixed $treeValue = null,
        string|null $depthAttribute = null,
        int|null $childDepth = null,
        int|null $maxRelativeDepth = null,
    ): array {
        $condition = [
            'and',
            ['<', $leftAttribute, $leftValue],
            ['>', $rightAttribute, $rightValue],
        ];

        if ($depthAttribute !== null && $childDepth !== null && $maxRelativeDepth !== null) {
            $condition[] = ['>=', $depthAttribute, $childDepth - $maxRelativeDepth];
        }

        if ($treeAttribute !== false) {
            $condition[] = [$treeAttribute => $treeValue];
        }

        return $condition;
    }

    /**
     * Creates a condition array for finding the previous sibling of a node.
     *
     * This method builds a condition that identifies the node whose right attribute is exactly one less than the left
     * attribute of the reference node.
     *
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $leftValue Reference node left value.
     * @param false|string $treeAttribute Name of tree attribute or `false` if disabled.
     * @param mixed $treeValue Tree value to filter by (ignored if treeAttribute is `false`).
     *
     * @return array Condition array for finding the previous sibling.
     *
     * Usage example:
     * ```php
     * $condition = QueryConditionBuilder::createPrevSiblingCondition('rgt', $node->lft, 'tree', $node->tree);
     * $prevSibling = MyModel::find()->andWhere($condition)->one();
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createPrevSiblingCondition(
        string $rightAttribute,
        int $leftValue,
        string|false $treeAttribute = false,
        mixed $treeValue = null,
    ): array {
        $condition = [$rightAttribute => $leftValue - 1];

        if ($treeAttribute !== false) {
            $condition = ['and', $condition, [$treeAttribute => $treeValue]];
        }

        return $condition;
    }

    /**
     * Creates a condition array for finding nodes within a range of left and right values.
     *
     * This is the core condition builder for nested sets operations, used to identify nodes within a specific subtree
     * boundary.
     *
     * It creates an 'and' condition that requires both the left attribute to be greater than or equal to the left
     * value, and the right attribute to be less than or equal to the right value.
     *
     * This condition is essential for operations like finding descendants, deleting subtrees, or moving nodes within
     * the nested set structure.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $leftValue Left boundary value.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $rightValue Right boundary value.
     * @param false|string $treeAttribute Name of tree attribute or `false` if disabled.
     * @param mixed $treeValue Tree value to filter by (ignored if treeAttribute is `false`).
     *
     * @return array Condition array for finding nodes within the specified range.
     *
     * Usage example:
     * ```php
     * $condition = QueryConditionBuilder::createRangeCondition('lft', 5, 'rgt', 10, 'tree', 1);
     * $descendants = MyModel::find()->andWhere($condition)->all();
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createRangeCondition(
        string $leftAttribute,
        int $leftValue,
        string $rightAttribute,
        int $rightValue,
        string|false $treeAttribute = false,
        mixed $treeValue = null,
    ): array {
        $condition = [
            'and',
            ['>=', $leftAttribute, $leftValue],
            ['<=', $rightAttribute, $rightValue],
        ];

        if ($treeAttribute !== false) {
            $condition[] = [$treeAttribute => $treeValue];
        }

        return $condition;
    }

    /**
     * Creates a condition array for shifting left/right attributes.
     *
     * This method builds a condition for nodes that need their left or right attributes updated during tree
     * restructuring operations.
     *
     * @param string $attribute Name of the attribute to check (left or right).
     * @param int $value Minimum value for the attribute.
     * @param false|string $treeAttribute Name of tree attribute or `false` if disabled.
     * @param mixed $treeValue Tree value to filter by (ignored if treeAttribute is `false`).
     *
     * @return array Condition array for shifting operations.
     *
     * Usage example:
     * ```php
     * $condition = QueryConditionBuilder::createShiftCondition('lft', 10, 'tree', 1);
     * MyModel::updateAll(['lft' => new Expression('lft + 2')], $condition);
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createShiftCondition(
        string $attribute,
        int $value,
        string|false $treeAttribute = false,
        mixed $treeValue = null,
    ): array {
        $condition = ['>=', $attribute, $value];

        if ($treeAttribute !== false) {
            $condition = ['and', $condition, [$treeAttribute => $treeValue]];
        }

        return $condition;
    }

    /**
     * Creates a condition array for moving a subtree to a different tree.
     *
     * This method builds a condition for identifying nodes in a subtree that need to be moved from one tree to another.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $leftValue Left boundary value of the subtree.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $rightValue Right boundary value of the subtree.
     * @param false|string $treeAttribute Name of tree attribute or `false` if disabled.
     * @param mixed $currentTreeValue Current tree value of the subtree.
     *
     * @return array Condition array for subtree movement.
     *
     * Usage example:
     * ```php
     * $condition = QueryConditionBuilder::createSubtreeMoveCondition('lft', 5, 'rgt', 10, 'tree', 1);
     * MyModel::updateAll(['tree' => 2], $condition);
     * ```
     *
     * @phpstan-return array<int|string, mixed>
     */
    public static function createSubtreeMoveCondition(
        string $leftAttribute,
        int $leftValue,
        string $rightAttribute,
        int $rightValue,
        string|false $treeAttribute,
        mixed $currentTreeValue,
    ): array {
        $condition = [
            'and',
            ['>=', $leftAttribute, $leftValue],
            ['<=', $rightAttribute, $rightValue],
        ];

        if ($treeAttribute !== false) {
            $condition[] = [$treeAttribute => $currentTreeValue];
        }

        return $condition;
    }

    /**
     * Creates a SQL expression for incrementing or decrementing an attribute by a specific offset.
     *
     * Generates a properly quoted SQL expression that adds or subtracts a value to an attribute, suitable for bulk
     * update operations in nested sets tree restructuring.
     *
     * This method ensures consistent expression formatting and proper column name quoting across different database
     * systems.
     *
     * @param Connection $db Database connection for proper column quoting.
     * @param string $attribute Name of the attribute to modify.
     * @param int $offset Amount to add to the attribute (can be negative for subtraction).
     *
     * @return Expression SQL expression for the attribute update.
     *
     * Usage example:
     * ```php
     * $expression = QueryConditionBuilder::createOffsetExpression($db, 'lft', 5);
     * // Result: `lft` + 5
     *
     * $expression = QueryConditionBuilder::createOffsetExpression($db, 'lft', -3);
     * // Result: `lft` - 3
     *
     * MyModel::updateAll(['lft' => $expression], $condition);
     * ```
     */
    private static function createOffsetExpression(Connection $db, string $attribute, int $offset): Expression
    {
        return new Expression($db->quoteColumnName($attribute) . sprintf('%+d', $offset));
    }
}
