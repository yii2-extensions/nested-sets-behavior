<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\stub;

use yii\db\ActiveRecord;
use yii\db\Exception;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\NodeContext;

/**
 * Extensible Nested Sets Behavior stub for testing method exposure and call tracking.
 *
 * Provides a test double for {@see NestedSetsBehavior} exposing protected methods and tracking their invocation for
 * unit testing purposes.
 *
 * This class enables direct invocation of internal behavior logic and records method calls, supporting fine-grained
 * assertions in test scenarios.
 *
 * It also allows manual manipulation of internal state for advanced test coverage.
 *
 * Key features:
 * - Allows manual state manipulation (node, operation).
 * - Exposes protected methods for direct testing.
 * - Supports cache invalidation tracking.
 * - Tracks method invocations for assertion.
 *
 * @phpstan-template T of ActiveRecord
 * @phpstan-extends NestedSetsBehavior<T>
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class ExtendableNestedSetsBehavior extends NestedSetsBehavior
{
    /**
     * Tracks method calls for assertions.
     *
     * @phpstan-var array<string, bool>
     */
    public array $calledMethods = [];

    /**
     * Indicates if the cache invalidation method was called.
     */
    public bool $invalidateCacheCalled = false;

    /**
     * @throws Exception if an unexpected error occurs during execution.
     */
    public function exposedBeforeInsertNode(int $value, int $depth): void
    {
        $this->calledMethods['beforeInsertNode'] = true;

        $this->beforeInsertNode($value, $depth);
    }

    /**
     * @throws Exception if an unexpected error occurs during execution.
     */
    public function exposedBeforeInsertRootNode(): void
    {
        $this->calledMethods['beforeInsertRootNode'] = true;

        $this->beforeInsertRootNode();
    }

    public function exposedMoveNode(ActiveRecord $node, int $value, int $depth): void
    {
        $this->calledMethods['moveNode'] = true;

        $context = new NodeContext(
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

    /**
     * @phpstan-return array<string, bool>
     */
    public function getCalledMethods(): array
    {
        return $this->calledMethods;
    }

    public function invalidateCache(): void
    {
        $this->invalidateCacheCalled = true;

        parent::invalidateCache();
    }

    public function resetMethodCallTracking(): void
    {
        $this->calledMethods = [];
    }

    public function setNode(ActiveRecord|null $node): void
    {
        $this->node = $node;
    }

    public function setOperation(string|null $operation): void
    {
        $this->operation = $operation;
    }

    public function wasMethodCalled(string $methodName): bool
    {
        return $this->calledMethods[$methodName] ?? false;
    }
}
