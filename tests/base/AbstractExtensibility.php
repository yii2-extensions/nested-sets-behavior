<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use LogicException;
use yii2\extensions\nestedsets\exception\Message;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{ExtendableMultipleTree, Tree};
use yii2\extensions\nestedsets\tests\support\stub\ExtendableNestedSetsBehavior;
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Unit tests for the protected {@see NestedSetsBehavior} methods that remain accessible to subclasses.
 *
 * @see ExtendableMultipleTree for extensible multi-tree model.
 * @see ExtendableNestedSetsBehavior for behavior subclass exposing protected methods.
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

    public function testThrowLogicExceptionWhenSubclassMovesNodeAsRootWithoutTreeAttribute(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'root']);

        $root->makeRoot();

        // Public makeRoot() rejects single-tree moves earlier; only a subclass can reach the protected move.
        $behavior = $root->attachBehavior('nestedSetsBehavior', ExtendableNestedSetsBehavior::class);

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $behavior,
            'Single-tree model must use the extendable behavior.',
        );
        self::assertFalse(
            $behavior->treeAttribute,
            'Tree attribute must be disabled.',
        );

        try {
            $behavior->exposedMoveNodeAsRoot();

            self::fail(
                'A cross-tree move without a tree attribute must be rejected.',
            );
        } catch (LogicException $exception) {
            self::assertSame(
                Message::TREE_ATTRIBUTE_REQUIRED->getMessage(),
                $exception->getMessage(),
                'Message must name the missing tree attribute.',
            );
            self::assertSame(
                ['lft' => 1, 'rgt' => 2, 'depth' => 0],
                Tree::findOne($root->getPrimaryKey())?->getAttributes(['lft', 'rgt', 'depth']),
                'Stored coordinates must stay unchanged.',
            );
        }
    }
}
