<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use yii2\extensions\nestedsets\tests\support\model\ExtendableMultipleTree;
use yii2\extensions\nestedsets\tests\support\stub\ExtendableNestedSetsBehavior;
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Base class for extensibility tests in nested sets tree behaviors.
 *
 * Provides a suite of unit tests to verify the extensibility and subclassing capabilities of the nested sets behavior,
 * ensuring protected methods remain accessible for customization and extension in descendant classes.
 *
 * This class validates that key internal methods of the nested sets behavior—such as node insertion, root insertion,
 * node movement, and attribute shifting—can be invoked and overridden by subclasses, supporting advanced use cases
 * and framework extensibility.
 *
 * The tests cover scenarios for exposing protected methods, confirming their correct execution and the ability to
 * customize node state during tree operations in both single-tree and multi-tree models.
 *
 * Key features.
 * - Ensures protected methods are accessible for subclass extension.
 * - Supports both single-tree and multi-tree model scenarios.
 * - Tests before-insert and move operations for extensibility.
 * - Validates extensibility for root and non-root node operations.
 * - Verifies correct attribute assignment by protected methods.
 *
 * @see ExtendableMultipleTree for extensible multi-tree model.
 * @see ExtendableNestedSetsBehavior for behavior subclass exposing protected methods.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
abstract class AbstractExtensibility extends TestCase
{
    public function testProtectedBeforeInsertNodeRemainsAccessibleToSubclasses(): void
    {
        $this->createDatabase();

        $testNode = new ExtendableMultipleTree(
            [
                'name' => 'Extensibility Test Node',
                'tree' => 1,
            ],
        );

        $extendableBehavior = $testNode->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $extendableBehavior,
            "'ExtendableMultipleTree' should use 'ExtendableNestedSetsBehavior'.",
        );

        $extendableBehavior->exposedBeforeInsertNode(5, 1);

        self::assertTrue(
            $extendableBehavior->wasMethodCalled('beforeInsertNode'),
            "'beforeInsertNode()' should remain protected to allow subclass customization.",
        );
        self::assertEquals(
            5,
            $testNode->lft,
            "'beforeInsertNode()' should set the 'left' attribute correctly.",
        );
        self::assertEquals(
            6,
            $testNode->rgt,
            "'beforeInsertNode()' should set the 'right' attribute correctly.",
        );
        self::assertEquals(
            1,
            $testNode->depth,
            "'beforeInsertNode()' should set the 'depth' attribute correctly.",
        );
    }

    public function testProtectedBeforeInsertRootNodeRemainsAccessibleToSubclasses(): void
    {
        $this->createDatabase();

        $rootTestNode = new ExtendableMultipleTree(
            [
                'name' => 'Root Test Node',
                'tree' => 2,
            ],
        );

        $rootBehavior = $rootTestNode->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $rootBehavior,
            "'ExtendableMultipleTree' should use 'ExtendableNestedSetsBehavior'.",
        );

        $rootBehavior->exposedBeforeInsertRootNode();

        self::assertTrue(
            $rootBehavior->wasMethodCalled('beforeInsertRootNode'),
            "'beforeInsertRootNode()' should remain protected to allow subclass customization.",
        );

        self::assertEquals(
            1,
            $rootTestNode->lft,
            "'beforeInsertRootNode()' should set 'left' attribute to '1'.",
        );
        self::assertEquals(
            2,
            $rootTestNode->rgt,
            "'beforeInsertRootNode()' should set 'right' attribute to '2'.",
        );
        self::assertEquals(
            0,
            $rootTestNode->depth,
            "'beforeInsertRootNode()' should set 'depth' attribute to '0'.",
        );
    }

    public function testProtectedMoveNodeAsRootRemainsAccessibleToSubclasses(): void
    {
        $this->createDatabase();

        $sourceNode = new ExtendableMultipleTree(
            [
                'name' => 'Source Node',
                'tree' => 5,
            ],
        );

        $sourceNode->makeRoot();
        $sourceBehavior = $sourceNode->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $sourceBehavior,
            "'ExtendableMultipleTree' should use 'ExtendableNestedSetsBehavior'.",
        );

        $sourceBehavior->exposedMoveNodeAsRoot();

        self::assertTrue(
            $sourceBehavior->wasMethodCalled('moveNodeAsRoot'),
            "'moveNodeAsRoot()' method should remain protected to allow subclass customization.",
        );
    }

    public function testProtectedMoveNodeRemainsAccessibleToSubclasses(): void
    {
        $this->createDatabase();

        $sourceNode = new ExtendableMultipleTree(
            [
                'name' => 'Source Node',
                'tree' => 4,
            ],
        );

        $sourceNode->makeRoot();

        $targetNode = new ExtendableMultipleTree(
            [
                'name' => 'Target Node',
                'tree' => 4,
            ],
        );

        $targetNode->appendTo($sourceNode);
        $sourceBehavior = $sourceNode->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $sourceBehavior,
            "'ExtendableMultipleTree' should use 'ExtendableNestedSetsBehavior'.",
        );

        $sourceBehavior->exposedMoveNode($targetNode, 5, 2);

        self::assertTrue(
            $sourceBehavior->wasMethodCalled('moveNode'),
            "'moveNode()' should remain protected to allow subclass customization.",
        );
    }

    public function testProtectedShiftLeftRightAttributeRemainsAccessibleToSubclasses(): void
    {
        $this->createDatabase();

        $parentNode = new ExtendableMultipleTree(
            [
                'name' => 'Parent Node',
                'tree' => 3,
            ],
        );

        $parentNode->makeRoot();

        $childNode = new ExtendableMultipleTree(
            [
                'name' => 'Child Node',
                'tree' => 3,
            ],
        );

        $childNode->appendTo($parentNode);
        $childBehavior = $childNode->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $childBehavior,
            "'ExtendableMultipleTree' should use 'ExtendableNestedSetsBehavior'.",
        );

        $childBehavior->exposedShiftLeftRightAttribute(1, 2);

        self::assertTrue(
            $childBehavior->wasMethodCalled('shiftLeftRightAttribute'),
            "'shiftLeftRightAttribute()' should remain protected to allow subclass customization.",
        );
    }
}
