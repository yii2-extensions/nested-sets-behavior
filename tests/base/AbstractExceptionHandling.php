<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use LogicException;
use Throwable;
use yii\base\NotSupportedException;
use yii\db\{Exception, StaleObjectException};
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\MultipleTree;
use yii2\extensions\nestedsets\tests\support\model\Tree;
use yii2\extensions\nestedsets\tests\TestCase;

abstract class AbstractExceptionHandling extends TestCase
{
    public function testThrowExceptionWhenAppendToNewNodeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is new record.');

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
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node->appendTo($childOfNode);
    }

    public function testThrowExceptionWhenAppendToTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' must exist before calling 'appendTo()' on another node.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->appendTo(new Tree());
    }

    public function testThrowExceptionWhenAppendToTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before calling 'appendTo()' on another node.");

        $childOfNode = Tree::findOne(9);

        self::assertNotNull($childOfNode, "Target node with ID '9' should exist before calling 'appendTo()' on it.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node->appendTo($childOfNode);
    }

    /**
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function testThrowExceptionWhenDeleteNodeIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not delete a node when it is new record.');

        $node->delete();
    }

    public function testThrowExceptionWhenDeleteWithChildrenIsCalledOnNewRecordNode(): void
    {
        $this->generateFixtureTree();

        $node = new Tree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not delete a node when it is new record.');

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
        $this->expectExceptionMessage('Can not create a node when the target node is root.');

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
            'Can not move a node when the target node is child.',
        );

        $node->insertAfter($childOfNode);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' must exist before attempting to 'insertAfter()' a new record.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->insertAfter(new Tree());
    }

    public function testThrowExceptionWhenInsertAfterTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before attempting to 'insertAfter()' the root node.");

        $rootNode = Tree::findOne(1);

        self::assertNotNull($rootNode, "Root node with ID '1' should exist before attempting to 'insertAfter()' it.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is root.');

        $node->insertAfter($rootNode);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' must exist before attempting to 'insertAfter()' itself.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node->insertAfter($node);
    }

    public function testThrowExceptionWhenInsertBeforeNewNodeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is new record.');

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
        $this->expectExceptionMessage('Can not create a node when the target node is root.');

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
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node->insertBefore($childOfNode);
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before calling 'insertBefore()' on a new record.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->insertBefore(new Tree());
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before calling 'insertBefore()' on another node.");

        $rootNode = Tree::findOne(1);

        self::assertNotNull($rootNode, "Root node with ID '1' should exist before calling 'insertBefore()' on it.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is root.');

        $node->insertBefore($rootNode);
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before calling 'insertBefore()' on itself.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

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
        $this->expectExceptionMessage(sprintf('"%s" must have a primary key.', $node::class));

        $node->makeRoot();
    }

    public function testThrowExceptionWhenMakeRootOnNonRootNodeWithTreeAttributeFalse(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before calling 'makeRoot()' on it in 'Tree'.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node as the root when "treeAttribute" is false.');

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
        $this->expectExceptionMessage('Can not move the root node as the root.');

        $node->makeRoot();
    }

    public function testThrowExceptionWhenMakeRootWithTreeAttributeFalseAndRootExists(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'Root']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create more than one root when "treeAttribute" is false.');

        $node->makeRoot();
    }

    public function testThrowExceptionWhenPrependToTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' must exist before calling 'prependTo()' on another node.");

        $childOfNode = Tree::findOne(11);

        self::assertNotNull($childOfNode, "Target node with ID '11' must exist before calling 'prependTo()' on it.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node->prependTo($childOfNode);
    }

    public function testThrowExceptionWhenPrependToTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' must exist before calling 'prependTo()' on another node.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->prependTo(new Tree());
    }

    public function testThrowExceptionWhenPrependToTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before calling 'prependTo()' on itself.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node->prependTo($node);
    }

    public function testThrowLogicExceptionWhenBehaviorIsDetachedFromOwner(): void
    {
        $this->createDatabase();

        $node = new Tree(['name' => 'Root']);

        $behavior = $node->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(NestedSetsBehavior::class, $behavior);

        $node->detachBehavior('nestedSetsBehavior');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "owner" property must be set before using the behavior.');

        $behavior->parents();
    }

    public function testThrowLogicExceptionWhenBehaviorIsNotAttachedToOwner(): void
    {
        $behavior = new NestedSetsBehavior();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "owner" property must be set before using the behavior.');

        $behavior->parents();
    }

    /**
     * @throws StaleObjectException
     * @throws Throwable
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
            'Method "yii2\extensions\nestedsets\tests\support\model\Tree::delete" is not supported for deleting root nodes.',
        );

        $node->delete();
    }

    /**
     * @throws Throwable
     */
    public function testThrowNotSupportedExceptionWhenInsertIsCalledOnTree(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'Node']);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Method "yii2\extensions\nestedsets\tests\support\model\Tree::insert" is not supported for inserting new nodes.',
        );

        $node->insert();
    }
}
