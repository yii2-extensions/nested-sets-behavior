<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

/**
 * Builder for nested sets query conditions.
 *
 * Centralizes the creation of common query conditions used throughout the nested sets behavior, eliminating code
 * duplication and providing a consistent interface for building database conditions.
 *
 * This class handles range conditions, tree attribute conditions, and complex logical combinations, ensuring that all
 * conditions follow the same patterns and can be maintained.
 *
 * Key features.
 * - Boundary range conditions for left/right attributes.
 * - Centralized tree attribute condition handling.
 * - Complex condition combination with logical operators.
 * - Consistent condition structure across all operations.
 * - Type-safe condition building with proper validation.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class QueryConditionBuilder
{
    public function __construct(
        private readonly string $leftAttribute,
        private readonly string $rightAttribute,
        private readonly string|false $treeAttribute,
    ) {}

    /**
     * Creates a range condition for nodes within specified boundaries.
     *
     * @param int $leftValue Left boundary value.
     * @param int $rightValue Right boundary value.
     *
     * @return array Range condition array.
     *
     * @phpstan-return array<int, list<int|string>|string>
     */
    public function createRangeCondition(int $leftValue, int $rightValue): array
    {
        return [
            'and',
            ['>=', $this->leftAttribute, $leftValue],
            ['<=', $this->rightAttribute, $rightValue],
        ];
    }

    /**
     * Creates a condition for nodes at or after a specific position.
     *
     * @param string $attribute Attribute name ('lft' or 'rgt').
     * @param int $value Minimum value.
     *
     * @return array Greater-than-or-equal condition.
     *
     * @phpstan-return array<int, int|string>
     */
    public function createGteCondition(string $attribute, int $value): array
    {
        return [
            '>=',
            $attribute,
            $value,
        ];
    }

    /**
     * Creates a condition for nodes at or before a specific position.
     *
     * @param string $attribute Attribute name ('lft' or 'rgt').
     * @param int $value Maximum value.
     *
     * @return array Less-than-or-equal condition.
     *
     * @phpstan-return array<int, int|string>
     */
    public function createLteCondition(string $attribute, int $value): array
    {
        return [
            '<=',
            $attribute,
            $value,
        ];
    }

    /**
     * Creates a tree-scoped condition by adding tree attribute constraint.
     *
     * @param array $baseCondition Base condition to scope.
     * @param mixed $treeValue Tree attribute value for scoping.
     *
     * @return array Tree-scoped condition.
     *
     * @phpstan-param array<int, list<int|string>|string|int> $baseCondition
     *
     * @phpstan-return array<string, mixed>|array<int, array<string, mixed>|string>
     */
    public function createTreeScopedCondition(array $baseCondition, mixed $treeValue): array
    {
        if ($this->treeAttribute === false) {
            return $baseCondition;
        }

        return [
            'and',
            $baseCondition,
            [$this->treeAttribute => $treeValue],
        ];
    }

    /**
     * Creates a subtree deletion condition for a node and all its descendants.
     *
     * @param int $leftValue Left boundary of the subtree.
     * @param int $rightValue Right boundary of the subtree.
     * @param mixed $treeValue Tree attribute value for scoping.
     *
     * @return array Subtree deletion condition.
     *
     * @phpstan-return array<int|string, mixed>
     */
    public function createSubtreeCondition(int $leftValue, int $rightValue, mixed $treeValue): array
    {
        return $this->createTreeScopedCondition($this->createRangeCondition($leftValue, $rightValue), $treeValue);
    }

    /**
     * Creates a condition for moving nodes within the same tree boundaries.
     *
     * @param int $leftValue Left boundary of the moving subtree.
     * @param int $rightValue Right boundary of the moving subtree.
     * @param mixed $treeValue Tree attribute value for scoping.
     *
     * @return array Movement condition for same-tree operations.
     *
     * @phpstan-return array<int|string, mixed>
     */
    public function createMovementCondition(int $leftValue, int $rightValue, mixed $treeValue): array
    {
        return $this->createSubtreeCondition($leftValue, $rightValue, $treeValue);
    }

    /**
     * Creates a condition for cross-tree operations.
     *
     * @param string $attribute Attribute to filter ('lft' or 'rgt').
     * @param int $value Threshold value.
     * @param mixed $treeValue Tree attribute value for scoping.
     *
     * @return array Cross-tree operation condition.
     *
     * @phpstan-return array<int|string, mixed>
     */
    public function createCrossTreeCondition(string $attribute, int $value, mixed $treeValue): array
    {
        return $this->createTreeScopedCondition($this->createGteCondition($attribute, $value), $treeValue);
    }
}
