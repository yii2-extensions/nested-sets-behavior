<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use LogicException;
use Throwable;
use yii\base\NotSupportedException;
use yii\db\{Exception, StaleObjectException};
use yii2\extensions\nestedsets\exception\Message;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Unit tests for the exceptions that {@see NestedSetsBehavior} raises on invalid tree operations.
 *
 * @see MultipleTree for multi-tree model.
 * @see Tree for single-tree model.
 */
abstract class AbstractExceptionHandling extends TestCase
{
    public function testThrowExceptionWhenAppendToNewNodeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_CREATE_NODE_TARGET_IS_NEW_RECORD->getMessage(),
        );

        $node->appendTo(new Tree());
    }

    public function testThrowExceptionWhenAppendToTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Expected node with ID '9' to exist before calling 'appendTo()' on another node.",
        );

        $childOfNode = Tree::findOne(11);

        self::assertNotNull(
            $childOfNode,
            "Expected target child node with ID '11' to exist before calling 'appendTo()' on it.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_CHILD->getMessage(),
        );

        $node->appendTo($childOfNode);
    }

    public function testThrowExceptionWhenAppendToTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before calling 'appendTo()' on another node.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_NEW_RECORD->getMessage(),
        );

        $node->appendTo(new Tree());
    }

    public function testThrowExceptionWhenAppendToTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'appendTo()' on another node.",
        );

        $childOfNode = Tree::findOne(9);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '9' should exist before calling 'appendTo()' on it.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_SAME->getMessage(),
        );

        $node->appendTo($childOfNode);
    }

    /**
     * @throws StaleObjectException if optimistic, locking is enabled and the data to be deleted has been modified by
     * another process.
     * @throws Throwable if an unexpected error occurs during execution.
     */
    public function testThrowExceptionWhenDeleteNodeIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_DELETE_NEW_RECORD->getMessage(),
        );

        $node->delete();
    }

    public function testThrowExceptionWhenDeleteWithChildrenIsCalledOnNewRecordNode(): void
    {
        $this->generateFixtureTree();

        $node = new Tree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_DELETE_NEW_RECORD->getMessage(),
        );

        $node->deleteWithChildren();
    }

    public function testThrowExceptionWhenInsertAfterNewNodeTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $rootNode = Tree::findOne(1);

        self::assertNotNull(
            $rootNode,
            "Root node with ID '1' should exist before calling 'insertAfter()' on it in 'Tree'.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_CREATE_NODE_TARGET_IS_ROOT->getMessage(),
        );

        $node->insertAfter($rootNode);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before attempting to insert after its child node.",
        );

        $childOfNode = Tree::findOne(11);

        self::assertNotNull(
            $childOfNode,
            "Child node with ID '11' must exist before attempting to 'insertAfter()' it.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_CHILD->getMessage(),
        );

        $node->insertAfter($childOfNode);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before attempting to 'insertAfter()' a new record.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_NEW_RECORD->getMessage(),
        );

        $node->insertAfter(new Tree());
    }

    public function testThrowExceptionWhenInsertAfterTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before attempting to 'insertAfter()' the root node.",
        );

        $rootNode = Tree::findOne(1);

        self::assertNotNull(
            $rootNode,
            "Root node with ID '1' should exist before attempting to 'insertAfter()' it.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_ROOT->getMessage(),
        );

        $node->insertAfter($rootNode);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before attempting to 'insertAfter()' itself.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_SAME->getMessage(),
        );

        $node->insertAfter($node);
    }

    public function testThrowExceptionWhenInsertBeforeNewNodeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_CREATE_NODE_TARGET_IS_NEW_RECORD->getMessage(),
        );

        $node->insertBefore(new Tree());
    }

    public function testThrowExceptionWhenInsertBeforeNewNodeTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);
        $rootNode = Tree::findOne(1);

        self::assertNotNull(
            $rootNode,
            "Root node with ID '1' should exist before calling 'insertBefore()' on it in 'Tree'.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_CREATE_NODE_TARGET_IS_ROOT->getMessage(),
        );

        $node->insertBefore($rootNode);
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before calling 'insertBefore()' on another node.",
        );

        $childOfNode = Tree::findOne(11);

        self::assertNotNull(
            $childOfNode,
            "Target child node with ID '11' must exist before calling 'insertBefore()' on it.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_CHILD->getMessage(),
        );

        $node->insertBefore($childOfNode);
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'insertBefore()' on a new record.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_NEW_RECORD->getMessage(),
        );

        $node->insertBefore(new Tree());
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'insertBefore()' on another node.",
        );

        $rootNode = Tree::findOne(1);

        self::assertNotNull(
            $rootNode,
            "Root node with ID '1' should exist before calling 'insertBefore()' on it.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_ROOT->getMessage(),
        );

        $node->insertBefore($rootNode);
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'insertBefore()' on itself.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_SAME->getMessage(),
        );

        $node->insertBefore($node);
    }

    public function testThrowExceptionWhenMakeRootIsCalledOnModelWithoutPrimaryKey(): void
    {
        $this->createDatabase();

        $node = new class (['name' => 'Root without PK']) extends MultipleTree {
            public static function primaryKey(): array
            {
                return [];
            }

            public function makeRoot(): bool
            {
                return parent::makeRoot();
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::PRIMARY_KEY_REQUIRED->getMessage($node::class),
        );

        $node->makeRoot();
    }

    public function testThrowExceptionWhenMakeRootOnNonRootNodeWithTreeAttributeFalse(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'makeRoot()' on it in 'Tree'.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_AS_ROOT->getMessage(),
        );

        $node->makeRoot();
    }

    public function testThrowExceptionWhenMakeRootOnRootNodeInMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(23);

        self::assertNotNull(
            $node,
            "Node with ID '23' should exist before calling 'makeRoot()' on it in 'MultipleTree'.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_ROOT_AS_ROOT->getMessage(),
        );

        $node->makeRoot();
    }

    public function testThrowExceptionWhenMakeRootWithTreeAttributeFalseAndRootExists(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'Root']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_CREATE_MULTIPLE_ROOTS->getMessage(),
        );

        $node->makeRoot();
    }

    public function testThrowExceptionWhenPrependToTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before calling 'prependTo()' on another node.",
        );

        $childOfNode = Tree::findOne(11);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '11' must exist before calling 'prependTo()' on it.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_CHILD->getMessage(),
        );

        $node->prependTo($childOfNode);
    }

    public function testThrowExceptionWhenPrependToTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before calling 'prependTo()' on another node.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_NEW_RECORD->getMessage(),
        );

        $node->prependTo(new Tree());
    }

    public function testThrowExceptionWhenPrependToTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'prependTo()' on itself.",
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::CANNOT_MOVE_NODE_TARGET_IS_SAME->getMessage(),
        );

        $node->prependTo($node);
    }

    public function testThrowLogicExceptionWhenBehaviorIsDetachedFromOwner(): void
    {
        $this->createDatabase();

        $node = new Tree(['name' => 'Root']);

        $behavior = $node->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            NestedSetsBehavior::class,
            $behavior,
            'Behavior must be an instance of NestedSetsBehavior.',
        );

        $node->detachBehavior('nestedSetsBehavior');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            Message::BEHAVIOR_NOT_ATTACHED->getMessage(),
        );

        $behavior->parents();
    }

    public function testThrowLogicExceptionWhenBehaviorIsNotAttachedToOwner(): void
    {
        $behavior = new NestedSetsBehavior();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            Message::BEHAVIOR_NOT_ATTACHED->getMessage(),
        );

        $behavior->parents();
    }

    /**
     * @throws StaleObjectException if optimistic, locking is enabled and the data to be deleted has been modified by
     * another process.
     * @throws Throwable if an unexpected error occurs during execution.
     */
    public function testThrowNotSupportedExceptionWhenDeleteIsCalledOnRootNode(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(1);

        self::assertNotNull(
            $node,
            "Node with ID '1' should exist before attempting deletion.",
        );

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            Message::DELETE_ROOT_NOT_SUPPORTED->getMessage(Tree::class),
        );

        $node->delete();
    }

    /**
     * @throws Throwable if an unexpected error occurs during execution.
     */
    public function testThrowNotSupportedExceptionWhenInsertIsCalledOnTree(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'Node']);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            Message::INSERT_NOT_SUPPORTED->getMessage(Tree::class),
        );

        $node->insert();
    }
}
