<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use yii\db\ActiveRecord;

/**
 * Immutable context object containing all necessary data for node movement operations.
 *
 * Encapsulates target node, operation type, and derived values to eliminate parameter passing and provide type safety
 * for nested set operations.
 *
 * Key features.
 * - Calculated positioning values.
 * - Immutable value object design.
 * - Operation-specific factory methods.
 * - PHPStan compatibility.
 * - Type-safe node references.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class NodeContext
{
    public function __construct(
        private readonly ActiveRecord $targetNode,
        public readonly int $targetPositionValue,
        public readonly int $depthLevelDelta,
    ) {}

    /**
     * Creates context for append-to operation (last child).
     *
     * @param ActiveRecord $targetNode Target node to append to.
     * @param string $rightAttribute Name of the right attribute.
     *
     * @return self New instance with the specified parameters for append-to operation.
     *
     * @phpstan-param 'rgt' $rightAttribute
     */
    public static function forAppendTo(ActiveRecord $targetNode, string $rightAttribute): self
    {
        return new self(
            targetNode: $targetNode,
            targetPositionValue: $targetNode->getAttribute($rightAttribute),
            depthLevelDelta: 1,
        );
    }

    /**
     * Creates context for insert-after operation (next sibling).
     *
     * @param ActiveRecord $targetNode Target node to insert after.
     * @param string $rightAttribute Name of the right attribute.
     *
     * @return self New instance with the specified parameters for insert-after operation.
     *
     * @phpstan-param 'rgt' $rightAttribute
     */
    public static function forInsertAfter(ActiveRecord $targetNode, string $rightAttribute): self
    {
        $rightValue = $targetNode->getAttribute($rightAttribute);

        return new self(
            targetNode: $targetNode,
            targetPositionValue: $rightValue + 1,
            depthLevelDelta: 0,
        );
    }

    /**
     * Creates context for insert-before operation (previous sibling).
     *
     * @param ActiveRecord $targetNode Target node to insert before.
     * @param string $leftAttribute Name of the left attribute.
     *
     * @return self New instance with the specified parameters for insert-before operation.
     *
     * @phpstan-param 'lft' $leftAttribute
     */
    public static function forInsertBefore(ActiveRecord $targetNode, string $leftAttribute): self
    {
        $leftValue = $targetNode->getAttribute($leftAttribute);

        return new self(
            targetNode: $targetNode,
            targetPositionValue: $leftValue,
            depthLevelDelta: 0,
        );
    }

    /**
     * Creates context for prepend-to operation (first child).
     *
     * @param ActiveRecord $targetNode Target node to prepend to.
     * @param string $leftAttribute Name of the left attribute.
     *
     * @return self New instance with the specified parameters for prepend-to operation.
     *
     * @phpstan-param 'lft' $leftAttribute
     */
    public static function forPrependTo(ActiveRecord $targetNode, string $leftAttribute): self
    {
        $leftValue = $targetNode->getAttribute($leftAttribute);

        return new self(
            targetNode: $targetNode,
            targetPositionValue: $leftValue + 1,
            depthLevelDelta: 1,
        );
    }

    /**
     * Returns the depth value of the target node.
     *
     * @param string $depthAttribute Name of the depth attribute.
     *
     * @return int Depth value of the target node.
     *
     * @phpstan-param 'depth' $depthAttribute
     */
    public function getTargetDepth(string $depthAttribute): int
    {
        return $this->targetNode->getAttribute($depthAttribute);
    }

    /**
     * Returns the tree attribute value of the target node.
     *
     * @param false|string $treeAttribute Name of the tree attribute or `false` if disabled.
     *
     * @return mixed Tree attribute value or `null` if tree attribute is disabled.
     */
    public function getTargetTreeValue(string|false $treeAttribute): mixed
    {
        return $treeAttribute !== false
            ? $this->targetNode->getAttribute($treeAttribute)
            : null;
    }
}
