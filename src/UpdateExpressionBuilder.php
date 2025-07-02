<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use yii\db\{Connection, Expression};

use function sprintf;

/**
 * Builder for nested sets update expressions and operations.
 *
 * Centralizes the creation of SQL expressions and update operations used throughout the nested sets behavior,
 * eliminating code duplication and providing a consistent interface for database updates.
 *
 * This class handles attribute offset expressions, bulk updates, and complex multi-attribute operations, ensuring that
 * all updates follow the same patterns and can be maintained.
 *
 * Key features.
 * - Bulk update operations for multiple attributes.
 * - Centralized SQL expression building with proper quoting.
 * - Consistent offset calculation and application.
 * - Cross-tree movement update handling.
 * - Type-safe expression building with validation.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class UpdateExpressionBuilder
{
    public function __construct(
        private readonly string $leftAttribute,
        private readonly string $rightAttribute,
        private readonly string $depthAttribute,
        private readonly string|false $treeAttribute,
        private readonly Connection $db,
    ) {}

    /**
     * Creates an SQL expression for attribute offset calculation.
     *
     * @param string $attribute Attribute name to offset.
     * @param int $offset Offset value (can be negative).
     *
     * @return Expression SQL expression for the offset operation.
     */
    public function createOffsetExpression(string $attribute, int $offset): Expression
    {
        return new Expression(
            $this->db->quoteColumnName($attribute) . sprintf('%+d', $offset),
        );
    }

    /**
     * Creates update attributes array for shifting left/right boundaries.
     *
     * @param int $offset Offset to apply to both attributes.
     *
     * @return array<string, Expression> Update attributes with expressions.
     */
    public function createShiftUpdateAttributes(int $offset): array
    {
        return [
            $this->leftAttribute => $this->createOffsetExpression($this->leftAttribute, $offset),
            $this->rightAttribute => $this->createOffsetExpression($this->rightAttribute, $offset),
        ];
    }

    /**
     * Creates update attributes for depth offset.
     *
     * @param int $depthOffset Depth offset to apply.
     *
     * @return array<string, Expression> Update attributes for depth.
     */
    public function createDepthUpdateAttributes(int $depthOffset): array
    {
        return [
            $this->depthAttribute => $this->createOffsetExpression($this->depthAttribute, $depthOffset),
        ];
    }

    /**
     * Creates complete subtree movement update attributes.
     *
     * @param int $positionOffset Offset for left/right attributes.
     * @param int $depthOffset Offset for depth attribute.
     * @param mixed $targetTreeValue New tree attribute value.
     *
     * @return array<string, mixed> Complete update attributes array.
     */
    public function createSubtreeMovementAttributes(
        int $positionOffset,
        int $depthOffset,
        mixed $targetTreeValue,
    ): array {
        $attributes = [
            $this->leftAttribute => $this->createOffsetExpression($this->leftAttribute, $positionOffset),
            $this->rightAttribute => $this->createOffsetExpression($this->rightAttribute, $positionOffset),
            $this->depthAttribute => $this->createOffsetExpression($this->depthAttribute, $depthOffset),
        ];

        if ($this->treeAttribute !== false) {
            $attributes[$this->treeAttribute] = $targetTreeValue;
        }

        return $attributes;
    }

    /**
     * Creates update attributes for single attribute offset.
     *
     * @param string $attribute Attribute name to update.
     * @param int $offset Offset value to apply.
     *
     * @return array<string, Expression> Single attribute update array.
     */
    public function createSingleAttributeUpdate(string $attribute, int $offset): array
    {
        return [
            $attribute => $this->createOffsetExpression($attribute, $offset),
        ];
    }

    /**
     * Creates update attributes for cross-tree boundary adjustment.
     *
     * @param int $boundaryOffset Offset for boundary adjustment.
     *
     * @return array<string, Expression> Boundary adjustment attributes.
     */
    public function createBoundaryAdjustmentAttributes(int $boundaryOffset): array
    {
        return $this->createShiftUpdateAttributes($boundaryOffset);
    }
}
