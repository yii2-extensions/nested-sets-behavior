<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\stub;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * @phpstan-template T of ActiveRecord
 *
 * @phpstan-extends NestedSetsBehavior<T>
 */
final class ExtendableNestedSetsBehavior extends NestedSetsBehavior
{
    /**
     * @phpstan-var array<string, bool>
     */
    public array $calledMethods = [];

    /**
     * @phpstan-param array<int|string, mixed> $condition
     */
    public function exposedApplyTreeAttributeCondition(array &$condition): void
    {
        $this->calledMethods['applyTreeAttributeCondition'] = true;

        $this->applyTreeAttributeCondition($condition);
    }

    public function exposedBeforeInsertNode(int $value, int $depth): void
    {
        $this->calledMethods['beforeInsertNode'] = true;

        $this->beforeInsertNode($value, $depth);
    }

    public function exposedBeforeInsertRootNode(): void
    {
        $this->calledMethods['beforeInsertRootNode'] = true;

        $this->beforeInsertRootNode();
    }

    public function exposedDeleteWithChildrenInternal(): bool|int
    {
        $this->calledMethods['deleteWithChildrenInternal'] = true;

        return $this->deleteWithChildrenInternal();
    }

    public function exposedMoveNode(ActiveRecord $node, int $value, int $depth): void
    {
        $this->calledMethods['moveNode'] = true;

        // Create a mock context for testing compatibility
        $context = new \yii2\extensions\nestedsets\NodeContext(
            $node,
            0,
            0,
        );
        $this->moveNode($context);
    }

    public function exposedMoveNodeAsRoot(): void
    {
        $this->calledMethods['moveNodeAsRoot'] = true;

        $this->moveNodeAsRoot(null);
    }

    public function exposedShiftLeftRightAttribute(int $value, int $delta): void
    {
        $this->calledMethods['shiftLeftRightAttribute'] = true;

        $this->shiftLeftRightAttribute($value, $delta);
    }

    public function resetMethodCallTracking(): void
    {
        $this->calledMethods = [];
    }

    public function wasMethodCalled(string $methodName): bool
    {
        return $this->calledMethods[$methodName] ?? false;
    }

    /**
     * @phpstan-return array<string, bool>
     */
    public function getCalledMethods(): array
    {
        return $this->calledMethods;
    }
}
