<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use yii\db\{Connection, Expression};

use function sprintf;

/**
 * Builds Yii condition arrays and offset expressions for nested set queries and bulk coordinate updates.
 */
final class QueryConditionBuilder
{
    /**
     * Returns a condition matching the descendants of a node, optionally limited to a maximum depth below it.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $leftValue Left value of the ancestor.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $rightValue Right value of the ancestor.
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     * @param mixed $treeValue Tree identifier to match; ignored when `$treeAttribute` is `false`.
     * @param string|null $depthAttribute Name of the depth attribute, or `null` to skip depth filtering.
     * @param int|null $parentDepth Depth of the ancestor; required together with `$maxRelativeDepth`.
     * @param int|null $maxRelativeDepth Maximum number of levels below the ancestor, or `null` for all descendants.
     *
     * @return array<int|string, mixed> Condition matching the descendants.
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
     * Returns a condition matching the nodes of a tree whose boundary value is at least the given value.
     *
     * Opens space in the destination tree before a subtree moves into it.
     *
     * @param string $attribute Name of the left or right boundary attribute to compare.
     * @param int $value Lowest boundary value to match, inclusive.
     * @param string $treeAttribute Name of the tree attribute.
     * @param mixed $treeValue Identifier of the tree that receives the subtree.
     *
     * @return array<int|string, mixed> Condition matching the nodes to shift.
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
     * Returns a condition matching the leaf nodes strictly inside the given ancestor boundaries.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     * @param mixed $treeValue Tree identifier to match; ignored when `$treeAttribute` is `false`.
     * @param int|null $parentLeftValue Left value of the ancestor whose leaves are matched.
     * @param int|null $parentRightValue Right value of the ancestor whose leaves are matched.
     *
     * @return array<int|string, mixed> Condition matching the leaf descendants.
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
     * Returns a condition matching the node whose left value immediately follows the given right value.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $rightValue Right value of the reference node.
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     * @param mixed $treeValue Tree identifier to match; ignored when `$treeAttribute` is `false`.
     *
     * @return array<int|string, mixed> Condition matching the next sibling.
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
     * Returns column expressions for `updateAll()` that add a signed offset to each given attribute.
     *
     * @param Connection $db Connection used to quote the column names.
     * @param array<string, int> $attributeOffsets Signed offsets indexed by attribute name.
     *
     * @return array<string, Expression> Offset expressions indexed by attribute name.
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
     * Returns a condition matching the ancestors of a node, optionally limited to a maximum number of levels above it.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $leftValue Left value of the node.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $rightValue Right value of the node.
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     * @param mixed $treeValue Tree identifier to match; ignored when `$treeAttribute` is `false`.
     * @param string|null $depthAttribute Name of the depth attribute, or `null` to skip depth filtering.
     * @param int|null $childDepth Depth of the node; required together with `$maxRelativeDepth`.
     * @param int|null $maxRelativeDepth Maximum number of levels above the node, or `null` for all ancestors.
     *
     * @return array<int|string, mixed> Condition matching the ancestors.
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
     * Returns a condition matching the node whose right value immediately precedes the given left value.
     *
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $leftValue Left value of the reference node.
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     * @param mixed $treeValue Tree identifier to match; ignored when `$treeAttribute` is `false`.
     *
     * @return array<int|string, mixed> Condition matching the previous sibling.
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
     * Returns a condition matching the nodes whose boundaries lie within the given range, inclusive.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $leftValue Lowest left value to match.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $rightValue Highest right value to match.
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     * @param mixed $treeValue Tree identifier to match; ignored when `$treeAttribute` is `false`.
     *
     * @return array<int|string, mixed> Condition matching the subtree, including its root.
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
     * Returns a condition matching the nodes whose boundary value is at least the given value.
     *
     * @param string $attribute Name of the left or right boundary attribute to compare.
     * @param int $value Lowest boundary value to match, inclusive.
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     * @param mixed $treeValue Tree identifier to match; ignored when `$treeAttribute` is `false`.
     *
     * @return array<int|string, mixed> Condition matching the nodes to shift.
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
     * Returns a condition matching a subtree, including its root, within its current tree.
     *
     * @param string $leftAttribute Name of the left boundary attribute.
     * @param int $leftValue Left value of the subtree root.
     * @param string $rightAttribute Name of the right boundary attribute.
     * @param int $rightValue Right value of the subtree root.
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     * @param mixed $currentTreeValue Identifier of the tree that currently contains the subtree.
     *
     * @return array<int|string, mixed> Condition matching the subtree to move.
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
     * Returns an expression that adds a signed offset to the quoted column of an attribute.
     *
     * @param Connection $db Connection used to quote the column name.
     * @param string $attribute Name of the attribute to offset.
     * @param int $offset Signed amount to add.
     *
     * @return Expression Offset expression for a bulk update.
     */
    private static function createOffsetExpression(Connection $db, string $attribute, int $offset): Expression
    {
        return new Expression($db->quoteColumnName($attribute) . sprintf('%+d', $offset));
    }
}
