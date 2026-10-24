<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use yii\db\ActiveRecord;

/**
 * Represents the immutable target position and depth offset of a node insertion or move relative to a target node.
 */
final readonly class NodeContext
{
    /**
     * Creates a context for a position relative to the target node.
     *
     * @param ActiveRecord $targetNode Node the insertion or move is relative to.
     * @param int $targetPositionValue Left value the inserted or moved node takes.
     * @param int $depthLevelDelta Depth offset relative to the target node: `1` for a child, `0` for a sibling.
     */
    public function __construct(
        private ActiveRecord $targetNode,
        public int $targetPositionValue,
        public int $depthLevelDelta,
    ) {}

    /**
     * Creates the context that places a node as the last child of the target node.
     *
     * @param ActiveRecord $targetNode Node that becomes the parent.
     * @param 'rgt' $rightAttribute Name of the right boundary attribute.
     *
     * @return self Context positioned at the target right value.
     */
    public static function forAppendTo(ActiveRecord $targetNode, string $rightAttribute): self
    {
        return new self($targetNode, $targetNode->getAttribute($rightAttribute), 1);
    }

    /**
     * Creates the context that places a node as the next sibling of the target node.
     *
     * @param ActiveRecord $targetNode Node that becomes the previous sibling.
     * @param 'rgt' $rightAttribute Name of the right boundary attribute.
     *
     * @return self Context positioned right after the target right value.
     */
    public static function forInsertAfter(ActiveRecord $targetNode, string $rightAttribute): self
    {
        $rightValue = $targetNode->getAttribute($rightAttribute);

        return new self($targetNode, $rightValue + 1, 0);
    }

    /**
     * Creates the context that places a node as the previous sibling of the target node.
     *
     * @param ActiveRecord $targetNode Node that becomes the next sibling.
     * @param 'lft' $leftAttribute Name of the left boundary attribute.
     *
     * @return self Context positioned at the target left value.
     */
    public static function forInsertBefore(ActiveRecord $targetNode, string $leftAttribute): self
    {
        $leftValue = $targetNode->getAttribute($leftAttribute);

        return new self($targetNode, $leftValue, 0);
    }

    /**
     * Creates the context that places a node as the first child of the target node.
     *
     * @param ActiveRecord $targetNode Node that becomes the parent.
     * @param 'lft' $leftAttribute Name of the left boundary attribute.
     *
     * @return self Context positioned right after the target left value.
     */
    public static function forPrependTo(ActiveRecord $targetNode, string $leftAttribute): self
    {
        $leftValue = $targetNode->getAttribute($leftAttribute);

        return new self($targetNode, $leftValue + 1, 1);
    }

    /**
     * Returns the depth of the target node.
     *
     * @param 'depth' $depthAttribute Name of the depth attribute.
     *
     * @return int Depth of the target node.e
     */
    public function getTargetDepth(string $depthAttribute): int
    {
        return $this->targetNode->getAttribute($depthAttribute);
    }

    /**
     * Returns the tree identifier of the target node.
     *
     * @param false|string $treeAttribute Name of the tree attribute, or `false` when multiple trees are disabled.
     *
     * @return mixed Tree identifier, or `null` when `$treeAttribute` is `false`.
     */
    public function getTargetTreeValue(string|false $treeAttribute): mixed
    {
        return $treeAttribute !== false
            ? $this->targetNode->getAttribute($treeAttribute)
            : null;
    }
}
