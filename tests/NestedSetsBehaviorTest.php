<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use LogicException;
use Throwable;
use yii\base\NotSupportedException;
use yii\db\{ActiveRecord, Exception, StaleObjectException};
use yii\helpers\ArrayHelper;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{
    ExtendableMultipleTree,
    MultipleTree,
    Tree,
    TreeWithStrictValidation,
};
use yii2\extensions\nestedsets\tests\support\stub\ExtendableNestedSetsBehavior;

use function get_class;
use function sprintf;

final class NestedSetsBehaviorTest extends TestCase
{
    public function testReturnTrueAndMatchXmlAfterMakeRootNewForTreeAndMultipleTree(): void
    {
        $this->createDatabase();

        $nodeTree = new Tree(['name' => 'Root']);

        self::assertTrue(
            $nodeTree->makeRoot(),
            "'makeRoot()' should return 'true' when creating a new root node in 'Tree'.",
        );

        $nodeMultipleTree = new MultipleTree(['name' => 'Root 1']);

        self::assertTrue(
            $nodeMultipleTree->makeRoot(),
            "'makeRoot()' should return 'true' when creating the first root node in 'MultipleTree'.",
        );

        $nodeMultipleTree = new MultipleTree(['name' => 'Root 2']);

        self::assertTrue(
            $nodeMultipleTree->makeRoot(),
            "'makeRoot()' should return 'true' when creating a second root node in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-make-root-new.xml');

        self::assertSame(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'makeRoot()' must match the expected XML structure.",
        );
    }

    public function testThrowExceptionWhenMakeRootWithTreeAttributeFalseAndRootExists(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'Root']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create more than one root when "treeAttribute" is false.');

        $node->makeRoot();
    }

    public function testReturnTrueAndMatchXmlAfterPrependToNewNodeForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $childOfNode = Tree::findOne(9);

        self::assertNotNull(
            $childOfNode,
            "Node with ID '9' must exist before calling 'prependTo()' on it in 'Tree'.",
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            "'prependTo()' should return 'true' when prepending a new node to node '9' in 'Tree'.",
        );

        $node = new MultipleTree(['name' => 'New node']);

        $childOfNode = MultipleTree::findOne(31);

        self::assertNotNull(
            $childOfNode,
            "Node with ID '31' must exist before calling 'prependTo()' on it in 'MultipleTree'.",
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            "'prependTo()' should return 'true' when prepending a new node to node '31' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-prepend-to-new.xml');

        self::assertSame(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'prependTo()' must match the expected XML structure.",
        );
    }

    public function testThrowExceptionWhenAppendToNewNodeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is new record.');

        $node->appendTo(new Tree());
    }

    public function testReturnTrueAndMatchXmlAfterInsertBeforeNewForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $childOfNode = Tree::findOne(9);

        self::assertNotNull(
            $childOfNode,
            "Node with ID '9' should exist before calling 'insertBefore()' on it in 'Tree'.",
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            "'insertBefore()' should return 'true' when inserting a new node before node '9' in 'Tree'.",
        );

        $node = new MultipleTree(['name' => 'New node']);

        $childOfNode = MultipleTree::findOne(31);

        self::assertNotNull(
            $childOfNode,
            "Node with ID '31' should exist before calling 'insertBefore()' on it in 'MultipleTree'.",
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            "'insertBefore()' should return 'true' when inserting a new node before node '31' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-insert-before-new.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'insertBefore()' must match the expected XML structure.",
        );
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

    public function testReturnTrueAndMatchXmlAfterInsertAfterNewForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $childOfNode = Tree::findOne(9);

        self::assertNotNull(
            $childOfNode,
            "Node with ID '9' must exist before calling 'insertAfter()' on it in 'Tree'.",
        );

        self::assertTrue(
            $node->insertAfter($childOfNode),
            "'insertAfter()' should return 'true' when inserting a new node after node '9' in 'Tree'.",
        );

        $node = new MultipleTree(['name' => 'New node']);

        $childOfNode = MultipleTree::findOne(31);

        self::assertNotNull(
            $childOfNode,
            "Node with ID '31' must exist before calling 'insertAfter()' on it in 'MultipleTree'.",
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            "'insertAfter()' should return 'true' when inserting a new node after node '31' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-insert-after-new.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'insertAfter()' must match the expected XML structure.",
        );
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

    public function testReturnTrueAndMatchXmlAfterMakeRootOnExistingMultipleTreeNode(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' must exist before calling 'makeRoot()' on it in 'MultipleTree'.",
        );

        $node->name = 'Updated node 2';

        self::assertTrue(
            $node->makeRoot(),
            "'makeRoot()' should return 'true' when called on node '31' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-make-root-exists.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            "Resulting dataset after 'makeRoot()' must match the expected XML structure for 'MultipleTree'.",
        );
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

    public function testReturnTrueAndMatchXmlAfterPrependToUpForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before calling 'prependTo()' on another node in 'Tree'.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(2);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '2' must exist before calling 'prependTo()' on it in 'Tree'.",
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            "'prependTo()' should return 'true' when moving node '9' as child of node '2' in 'Tree'.",
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' must exist before calling 'prependTo()' on another node in 'MultipleTree'.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(24);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '24' must exist before calling 'prependTo()' on it in 'MultipleTree'.",
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            "'prependTo()' should return 'true' when moving node '31' as child of node '24' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-prepend-to-exists-up.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'prependTo()' must match the expected XML structure.",
        );
    }

    public function testReturnTrueAndMatchXmlAfterPrependToDownForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'prependTo()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(16);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '16' should exist before calling 'prependTo()' on it.",
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            "'prependTo()' should return 'true' when moving node '9' as child of node '16' in 'Tree'.",
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' should exist before calling 'prependTo()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(38);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '38' should exist before calling 'prependTo()' on it.",
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            "'prependTo()' should return 'true' when moving node '31' as child of node '38' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-prepend-to-exists-down.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'prependTo()' must match the expected XML structure.",
        );
    }

    public function testReturnTrueAndMatchXmlAfterPrependToMultipleTreeWhenTargetIsInAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before attempting to prepend to a node in another tree.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '53' must exist before attempting to prepend to it.",
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            "'prependTo()' should return 'true' when moving node '9' as child of node '53' in another tree.",
        );

        $simpleXML = $this->loadFixtureXML('test-prepend-to-exists-another-tree.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            "Resulting dataset after 'prependTo()' must match the expected XML structure for 'MultipleTree'.",
        );
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

    public function testReturnTrueAndMatchXmlAfterAppendToUpForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before calling 'appendTo()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(2);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '2' must exist before calling 'appendTo()' on it.",
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            "'appendTo()' should return 'true' when moving node '9' as child of node '2' in 'Tree'.",
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' must exist before calling 'appendTo()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(24);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '24' must exist before calling 'appendTo()' on it.",
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            "'appendTo()' should return 'true' when moving node '31' as child of node '24' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-append-to-exists-up.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'appendTo()' must match the expected XML structure.",
        );
    }

    public function testReturnTrueAndMatchXmlAfterAppendToDownForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'appendTo()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(16);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '16' should exist before calling 'appendTo()' on it.",
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            "'appendTo()' should return 'true' when moving node '9' as child of node '16' in 'Tree'.",
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' should exist before calling 'appendTo()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(38);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '38' should exist before calling 'appendTo()' on it.",
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            "'appendTo()' should return 'true' when moving node '31' as child of node '38' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-append-to-exists-down.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'appendTo()' must match the expected XML structure.",
        );
    }

    public function testReturnTrueAndMatchXmlAfterAppendToMultipleTreeWhenTargetIsInAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before attempting to 'appendTo()' a node in another tree.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '53' must exist before attempting to 'appendTo()' it.",
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            "'appendTo()' should return 'true' when moving node '9' as child of node '53' in another tree.",
        );

        $simpleXML = $this->loadFixtureXML('test-append-to-exists-another-tree.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            "Resulting dataset after 'appendTo()' must match the expected XML structure for 'MultipleTree'.",
        );
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

    public function testReturnTrueAndMatchXmlAfterInsertBeforeUpForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before calling 'insertBefore()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(2);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '2' must exist before calling 'insertBefore()' on it.",
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            "'insertBefore()' should return 'true' when moving node '9' before node '2' in 'Tree'.",
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' must exist before calling 'insertBefore()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(24);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '24' must exist before calling 'insertBefore()' on it.",
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            "'insertBefore()' should return 'true' when moving node '31' before node '24' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-insert-before-exists-up.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'insertBefore()' must match the expected XML structure.",
        );
    }

    public function testReturnTrueAndMatchXmlAfterInsertBeforeDownForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'insertBefore()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(16);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '16' should exist before calling 'insertBefore()' on it.",
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            "'insertBefore()' should return 'true' when moving node '9' before node '16' in 'Tree'.",
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' should exist before calling 'insertBefore()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(38);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '38' should exist before calling 'insertBefore()' on it.",
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            "'insertBefore()' should return 'true' when moving node '31' before node '38' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-insert-before-exists-down.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'insertBefore()' must match the expected XML structure.",
        );
    }

    public function testReturnTrueAndMatchXmlAfterInsertBeforeMultipleTreeWhenTargetIsInAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before attempting to insert before a node in another tree.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '53' must exist before attempting to insert before it.",
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            "'insertBefore()' should return 'true' when moving node '9' before node '53' in another tree.",
        );

        $simpleXML = $this->loadFixtureXML('test-insert-before-exists-another-tree.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            "Resulting dataset after 'insertBefore()' must match the expected XML structure for 'MultipleTree'.",
        );
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

    public function testThrowExceptionWhenInsertBeforeTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before calling 'insertBefore()' on itself.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node->insertBefore($node);
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

    public function testReturnTrueAndMatchXmlAfterInsertAfterUpForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before calling 'insertAfter()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(2);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '2' must exist before calling 'insertAfter()' on it.",
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            "'insertAfter()' should return 'true' when moving node '9' after node '2' in 'Tree'.",
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' must exist before calling 'insertAfter()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(24);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '24' must exist before calling 'insertAfter()' on it.",
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            "'insertAfter()' should return 'true' when moving node '31' after node '24' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-insert-after-exists-up.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'insertAfter()' must match the expected XML structure.",
        );
    }

    public function testReturnTrueAndMatchXmlAfterInsertAfterDownForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' should exist before calling 'insertAfter()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(16);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '16' should exist before calling 'insertAfter()' on it.",
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            "'insertAfter()' should return 'true' when moving node '9' after node '16' in 'Tree'.",
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            "Node with ID '31' should exist before calling 'insertAfter()' on another node.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(38);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '38' should exist before calling 'insertAfter()' on it.",
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            "'insertAfter()' should return 'true' when moving node '31' after node '38' in 'MultipleTree'.",
        );

        $simpleXML = $this->loadFixtureXML('test-insert-after-exists-down.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            "Resulting dataset after 'insertAfter()' must match the expected XML structure.",
        );
    }

    public function testReturnTrueAndMatchXmlAfterInsertAfterMultipleTreeWhenTargetIsInAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);

        self::assertNotNull(
            $node,
            "Node with ID '9' must exist before attempting to insert after a node in another tree.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '53' must exist before attempting to insert after it.",
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            "'insertAfter()' should return 'true' when moving node '9' after node '53' in another tree.",
        );

        $simpleXML = $this->loadFixtureXML('test-insert-after-exists-another-tree.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            "Resulting dataset after 'insertAfter()' must match the expected XML structure for 'MultipleTree'.",
        );
    }

    public function testThrowExceptionWhenInsertAfterTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' must exist before attempting to insert after a new record.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->insertAfter(new Tree());
    }

    public function testThrowExceptionWhenInsertAfterTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' must exist before attempting to insert after itself.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node->insertAfter($node);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' must exist before attempting to insert after its child node.");

        $childOfNode = Tree::findOne(11);

        self::assertNotNull($childOfNode, "Child node with ID '11' must exist before attempting to insert after it.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node->insertAfter($childOfNode);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before attempting to insert after the root node.");

        $rootNode = Tree::findOne(1);

        self::assertNotNull($rootNode, "Root node with ID '1' should exist before attempting to insert after it.");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is root.');

        $node->insertAfter($rootNode);
    }

    public function testReturnAffectedRowsAndMatchXmlAfterDeleteWithChildrenForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            7,
            Tree::findOne(9)?->deleteWithChildren(),
            "Deleting node with ID '9' and its children from 'Tree' should affect exactly seven rows.",
        );
        self::assertEquals(
            7,
            MultipleTree::findOne(31)?->deleteWithChildren(),
            "Deleting node with ID '31' and its children from 'MultipleTree' should affect exactly seven rows.",
        );

        $simpleXML = $this->loadFixtureXML('test-delete-with-children.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'XML dataset after deleting nodes with children should match the expected result.',
        );
    }

    public function testThrowExceptionWhenDeleteWithChildrenIsCalledOnNewRecordNode(): void
    {
        $this->generateFixtureTree();

        $node = new Tree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not delete a node when it is new record.');

        $node->deleteWithChildren();
    }

    /**
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function testReturnOneWhenDeleteNodeForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            1,
            Tree::findOne(9)?->delete(),
            "Deleting node with ID '9' from 'Tree' should affect exactly one row.",
        );
        self::assertEquals(
            1,
            MultipleTree::findOne(31)?->delete(),
            "Deleting node with ID '31' from 'MultipleTree' should affect exactly one row.",
        );

        $simpleXML = $this->loadFixtureXML('test-delete.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'XML dataset after deleting nodes should match the expected result.',
        );
    }

    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function testThrowExceptionWhenDeleteNodeIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not delete a node when it is new record.');

        $node->delete();
    }

    /**
     * @throws Throwable
     * @throws StaleObjectException
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

    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function testReturnOneWhenUpdateNodeName(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before attempting update.");

        $node->name = 'Updated node';

        self::assertEquals(1, $node->update(), 'Updating the node name should affect exactly one row.');
    }

    public function testReturnParentsForTreeAndMultipleTreeWithAndWithoutDepth(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-parents.php",
            ArrayHelper::toArray(Tree::findOne(11)?->parents()->all() ?? []),
            "Parents for 'Tree' node with ID '11' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-parents-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(33)?->parents()->all() ?? []),
            "Parents for 'MultipleTree' node with ID '33' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-parents-with-depth.php",
            ArrayHelper::toArray(Tree::findOne(11)?->parents(1)->all() ?? []),
            "Parents with 'depth=1' for 'Tree' node with ID '11' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-parents-multiple-tree-with-depth.php",
            ArrayHelper::toArray(MultipleTree::findOne(33)?->parents(1)->all() ?? []),
            "Parents with 'depth=1' for 'MultipleTree' node with ID '33' do not match the expected result.",
        );
    }

    public function testReturnChildrenForTreeAndMultipleTreeWithAndWithoutDepth(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-children.php",
            ArrayHelper::toArray(Tree::findOne(9)?->children()->all() ?? []),
            "Children for 'Tree' node with ID '9' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-children-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->children()->all() ?? []),
            "Children for 'MultipleTree' node with ID '31' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-children-with-depth.php",
            ArrayHelper::toArray(Tree::findOne(9)?->children(1)->all() ?? []),
            "Children with 'depth=1' for 'Tree' node with ID '9' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-children-multiple-tree-with-depth.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->children(1)->all() ?? []),
            "Children with 'depth=1' for 'MultipleTree' node with ID '31' do not match the expected result.",
        );
    }

    public function testReturnLeavesForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves.php",
            ArrayHelper::toArray(Tree::findOne(9)?->leaves()->all() ?? []),
            "Leaves for 'Tree' node with ID '9' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->leaves()->all() ?? []),
            "Leaves for 'MultipleTree' node with ID '31' do not match the expected result.",
        );
    }

    public function testReturnPrevNodesForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-prev.php",
            ArrayHelper::toArray(Tree::findOne(9)?->prev()->all() ?? []),
            "Previous nodes for 'Tree' node with ID '9' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-prev-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->prev()->all() ?? []),
            "Previous nodes for 'MultipleTree' node with ID '31' do not match the expected result.",
        );
    }

    public function testReturnNextNodesForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-next.php",
            ArrayHelper::toArray(Tree::findOne(9)?->next()->all() ?? []),
            "Next nodes for 'Tree' node with ID '9' do not match the expected result.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-next-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->next()->all() ?? []),
            "Next nodes for 'MultipleTree' node with ID '31' do not match the expected result.",
        );
    }

    public function testReturnIsRootForRootAndNonRootNode(): void
    {
        $this->generateFixtureTree();

        self::assertTrue(Tree::findOne(1)?->isRoot(), "Node with ID '1' should be identified as root.");
        self::assertFalse(Tree::findOne(2)?->isRoot(), "Node with ID '2' should not be identified as root.");
    }

    public function testReturnIsChildOfForMultipleTreeNodeUnderVariousAncestors(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(26);

        self::assertNotNull(
            $node,
            "Node with ID '26' should exist in the database.",
        );
        self::assertNotNull(
            $childOfNode = MultipleTree::findOne(25),
            "Node with ID '25' should exist in the database.",
        );
        self::assertTrue(
            $node->isChildOf($childOfNode),
            "Node with ID '26' should be a child of node with ID '25'.",
        );
        self::assertNotNull(
            $childOfNode = MultipleTree::findOne(23),
            "Node with ID '23' should exist in the database.",
        );
        self::assertTrue(
            $node->isChildOf($childOfNode),
            "Node with ID '26' should be a child of node with ID '23'.",
        );
        self::assertNotNull(
            $childOfNode = MultipleTree::findOne(3),
            "Node with ID '3' should exist in the database.",
        );
        self::assertFalse(
            $node->isChildOf($childOfNode),
            "Node with ID '26' should not be a child of node with ID '3'.",
        );
        self::assertNotNull(
            $childOfNode = MultipleTree::findOne(1),
            "Node with ID '1' should exist in the database.",
        );
        self::assertFalse(
            $node->isChildOf($childOfNode),
            "Node with ID '26' should not be a child of node with ID '1'.",
        );
    }

    public function testIsLeafReturnsTrueForLeafAndFalseForRoot(): void
    {
        $this->generateFixtureTree();

        self::assertTrue(
            Tree::findOne(4)?->isLeaf(),
            "Node with ID '4' should be a leaf node (no children).",
        );
        self::assertFalse(
            Tree::findOne(1)?->isLeaf(),
            "Node with ID '1' should not be a leaf node (has children or is root).",
        );
    }

    public function testThrowLogicExceptionWhenBehaviorIsNotAttachedToOwner(): void
    {
        $behavior = new NestedSetsBehavior();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "owner" property must be set before using the behavior.');

        $behavior->parents();
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

    public function testReturnFalseWhenDeleteWithChildrenIsAbortedByBeforeDelete(): void
    {
        $this->createDatabase();

        $node = $this->createPartialMock(
            Tree::class,
            [
                'beforeDelete',
            ],
        );
        $node->setAttributes(
            [
                'id' => 1,
                'name' => 'Test Node',
                'lft' => 1,
                'rgt' => 2,
                'depth' => 0,
            ],
        );
        $node->setIsNewRecord(false);
        $node->expects(self::once())->method('beforeDelete')->willReturn(false);

        self::assertFalse(
            $node->isTransactional(ActiveRecord::OP_DELETE),
            "Node with ID '1' should not use transactional delete when 'beforeDelete()' returns 'false'.",
        );

        $result = $node->deleteWithChildren();

        self::assertFalse(
            $result,
            "'deleteWithChildren()' should return 'false' when 'beforeDelete()' aborts the deletion process.",
        );
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
        $this->expectExceptionMessage(sprintf('"%s" must have a primary key.', get_class($node)));

        $node->makeRoot();
    }

    public function testSetNodeToNullAndCallBeforeInsertNodeSetsLftRgtAndDepth(): void
    {
        $this->createDatabase();

        $behavior = new class extends NestedSetsBehavior {
            public function callBeforeInsertNode(int $value, int $depth): void
            {
                $this->beforeInsertNode($value, $depth);
            }

            public function setNodeToNull(): void
            {
                $this->node = null;
            }

            public function getNodeDepth(): int|null
            {
                return $this->node !== null ? $this->node->getAttribute($this->depthAttribute) : null;
            }
        };

        $newNode = new Tree(['name' => 'Test Node']);

        $newNode->attachBehavior('testBehavior', $behavior);
        $behavior->setNodeToNull();
        $behavior->callBeforeInsertNode(5, 1);

        self::assertEquals(
            5,
            $newNode->lft,
            "'beforeInsertNode' should set 'lft' attribute to '5' on the new node.",
        );
        self::assertEquals(
            6,
            $newNode->rgt,
            "'beforeInsertNode' should set 'rgt' attribute to '6' on the new node.",
        );

        $actualDepth = $newNode->getAttribute('depth');

        self::assertEquals(
            1,
            $actualDepth,
            "'beforeInsertNode' method should set 'depth' attribute to '1' on the new node.",
        );
    }

    public function testAppendChildNodeToRootCreatesValidTreeStructure(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'Root']);

        $root->makeRoot();

        self::assertEquals(
            1,
            $root->lft,
            "Root node left value should be '1' after 'makeRoot()'.",
        );
        self::assertEquals(
            2,
            $root->rgt,
            "Root node right value should be '2' after 'makeRoot()'.",
        );
        self::assertEquals(
            0,
            $root->depth,
            "Root node depth should be '0' after 'makeRoot()'.",
        );

        $child = new Tree(['name' => 'Child']);

        try {
            $result = $child->appendTo($root);

            self::assertTrue(
                $result,
                "'appendTo()' should return 'true' when successfully appending a child node.",
            );

            $root->refresh();
            $child->refresh();

            self::assertGreaterThan(
                $child->lft,
                $child->rgt,
                "Child node right value should be greater than its left value after 'appendTo()'.",
            );
            self::assertEquals(
                1,
                $child->depth,
                "Child node depth should be '1' after being 'appendTo()' the root node.",
            );
        } catch (Exception $e) {
            self::fail('Real insertion failed: ' . $e->getMessage());
        }
    }

    public function testReturnShiftedLeftRightAttributesWhenChildAppendedToRoot(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'Root']);

        $root->makeRoot();
        $root->refresh();

        $child = new Tree(['name' => 'Child']);

        $child->appendTo($root);
        $child->refresh();

        self::assertEquals(
            1,
            $root->lft,
            "Root node left value should be '1' after 'makeRoot()' and appending a child.",
        );
        self::assertEquals(
            4,
            $root->rgt,
            "Root node right value should be '4' after 'makeRoot()' and appending a child.",
        );
        self::assertEquals(
            2,
            $child->lft,
            "Child node left value should be '2' after being 'appendTo()' to the root node.",
        );
        self::assertEquals(
            3,
            $child->rgt,
            "Child node right value should be '3' after being 'appendTo()' the root node.",
        );
        self::assertNotEquals(
            0,
            $child->lft,
            "Child node left value should not be '0' after 'appendTo()' operation.",
        );
        self::assertNotEquals(
            1,
            $child->rgt,
            "Child node right value should not be '1' after 'appendTo()' operation.",
        );
    }

    public function testAppendToWithRunValidationParameterUsingStrictValidation(): void
    {
        $this->generateFixtureTree();

        $targetNode = Tree::findOne(2);

        self::assertNotNull(
            $targetNode,
            "Target node with ID '2' should exist before calling 'appendTo()'.",
        );

        $invalidNode = new TreeWithStrictValidation(['name' => 'x']);

        $result1 = $invalidNode->appendTo($targetNode);
        $hasError1 = $invalidNode->hasErrors();

        self::assertFalse(
            $result1,
            "'appendTo()' should return 'false' when 'runValidation=true' and data fails validation.",
        );
        self::assertTrue(
            $hasError1,
            "Node should have validation errors when 'runValidation=true' and data is invalid.",
        );

        $invalidNode2 = new TreeWithStrictValidation(['name' => 'x']);

        $result2 = $invalidNode2->appendTo($targetNode, false);
        $hasError2 = $invalidNode2->hasErrors();

        self::assertTrue(
            $result2,
            "'appendTo()' should return 'true' when 'runValidation=false', even with invalid data that would " .
            'fail validation.',
        );
        self::assertFalse(
            $hasError2,
            "Node should not have validation errors when 'runValidation=false' because validation was skipped.",
        );

        $persistedNode = TreeWithStrictValidation::findOne($invalidNode2->id);

        self::assertNotNull(
            $persistedNode,
            'Node should exist in database after appending to target node with validation disabled.',
        );
    }

    public function testInsertAfterWithRunValidationParameterUsingStrictValidation(): void
    {
        $this->generateFixtureTree();

        $targetNode = Tree::findOne(9);

        self::assertNotNull(
            $targetNode,
            "Target node with ID '9' should exist before calling 'insertAfter()'.",
        );
        self::assertFalse(
            $targetNode->isRoot(),
            "Target node with ID '9' should not be root for 'insertAfter()' operation.",
        );

        $invalidNode = new TreeWithStrictValidation(['name' => 'x']);

        $result1 = $invalidNode->insertAfter($targetNode);
        $hasError1 = $invalidNode->hasErrors();

        self::assertFalse(
            $result1,
            "'insertAfter()' should return 'false' when 'runValidation=true' and data fails validation.",
        );
        self::assertTrue(
            $hasError1,
            "Node should have validation errors when 'runValidation=true' and data is invalid.",
        );

        $invalidNode2 = new TreeWithStrictValidation(['name' => 'x']);

        $result2 = $invalidNode2->insertAfter($targetNode, false);
        $hasError2 = $invalidNode2->hasErrors();

        self::assertTrue(
            $result2,
            "'insertAfter()' should return 'true' when 'runValidation=false', even with invalid data that would " .
            'fail validation.',
        );
        self::assertFalse(
            $hasError2,
            "Node should not have validation errors when 'runValidation=false' because validation was skipped.",
        );

        $persistedNode = TreeWithStrictValidation::findOne($invalidNode2->id);

        self::assertNotNull(
            $persistedNode,
            'Node should exist in database after inserting after target node with validation disabled.',
        );
    }

    public function testInsertBeforeWithRunValidationParameterUsingStrictValidation(): void
    {
        $this->generateFixtureTree();

        $targetNode = Tree::findOne(9);

        self::assertNotNull(
            $targetNode,
            "Target node with ID '9' should exist before calling 'insertBefore'.",
        );

        self::assertFalse(
            $targetNode->isRoot(),
            "Target node with ID '9' should not be root for 'insertBefore' operation.",
        );

        $invalidNode = new TreeWithStrictValidation(['name' => 'x']);

        $result1 = $invalidNode->insertBefore($targetNode);
        $hasError1 = $invalidNode->hasErrors();

        self::assertFalse(
            $result1,
            "'insertBefore()' should return 'false' when 'runValidation=true' and data fails validation.",
        );
        self::assertTrue(
            $hasError1,
            "Node should have validation errors when 'runValidation=true' and data is invalid.",
        );

        $invalidNode2 = new TreeWithStrictValidation(['name' => 'x']);

        $result2 = $invalidNode2->insertBefore($targetNode, false);
        $hasError2 = $invalidNode2->hasErrors();

        self::assertTrue(
            $result2,
            "'insertBefore()' should return 'true' when 'runValidation=false', even with invalid data that would " .
            'fail validation.',
        );
        self::assertFalse(
            $hasError2,
            "Node should not have validation errors when 'runValidation=false' because validation was skipped.",
        );

        $persistedNode = TreeWithStrictValidation::findOne($invalidNode2->id);

        self::assertNotNull(
            $persistedNode,
            'Node should exist in database after inserting before target node with validation disabled.',
        );
    }

    public function testMakeRootWithRunValidationParameterUsingStrictValidation(): void
    {
        $this->createDatabase();

        $invalidNode = new TreeWithStrictValidation(['name' => 'x']);

        $result1 = $invalidNode->makeRoot();
        $hasError1 = $invalidNode->hasErrors();

        self::assertFalse(
            $result1,
            "'makeRoot()' should return 'false' when 'runValidation=true' and data fails validation.",
        );
        self::assertTrue(
            $hasError1,
            "Node should have validation errors when 'runValidation=true' and data is invalid.",
        );

        $invalidNode2 = new TreeWithStrictValidation(['name' => 'x']);

        $result2 = $invalidNode2->makeRoot(false);
        $hasError2 = $invalidNode2->hasErrors();

        self::assertTrue(
            $result2,
            "'makeRoot()' should return 'true' when 'runValidation=false', even with invalid data that would fail " .
            'validation.',
        );
        self::assertFalse(
            $hasError2,
            "Node should not have validation errors when 'runValidation=false' because validation was skipped.",
        );

        $persistedNode = TreeWithStrictValidation::findOne($invalidNode2->id);

        self::assertNotNull(
            $persistedNode,
            "Node should exist in database after 'makeRoot()' with validation disabled.",
        );
        self::assertTrue(
            $persistedNode->isRoot(),
            "Node should be a root node after 'makeRoot()' operation.",
        );
        self::assertEquals(
            1,
            $persistedNode->lft,
            "Root node should have left value of '1'.",
        );
        self::assertEquals(
            2,
            $persistedNode->rgt,
            "Root node should have right value of '2'.",
        );
        self::assertEquals(
            0,
            $persistedNode->depth,
            "Root node should have depth of '0'.",
        );
    }

    public function testPrependToWithRunValidationParameterUsingStrictValidation(): void
    {
        $this->createDatabase();

        $parentNode = new TreeWithStrictValidation(['name' => 'Valid Parent']);

        $parentNode->makeRoot(false);

        $childNode = new TreeWithStrictValidation(
            [
                'name' => 'x',
            ],
        );

        $resultWithValidation = $childNode->prependTo($parentNode);
        $hasError1 = $childNode->hasErrors();

        self::assertFalse(
            $resultWithValidation,
            "'prependTo()' with 'runValidation=true' should return 'false' when validation fails.",
        );
        self::assertTrue(
            $hasError1,
            "Node should have validation errors when 'runValidation=true' and data is invalid.",
        );

        $childNode2 = new TreeWithStrictValidation(
            [
                'name' => 'x',
            ],
        );

        $resultWithoutValidation = $childNode2->prependTo($parentNode, false);
        $hasError2 = $childNode2->hasErrors();

        self::assertTrue(
            $resultWithoutValidation,
            "'prependTo()' with 'runValidation=false' should return 'true' when validation is skipped.",
        );
        self::assertFalse(
            $hasError2,
            "Node should not have validation errors when 'runValidation=false' because validation was skipped.",
        );
        self::assertSame(
            'x',
            $childNode2->name,
            "Node name should remain unchanged after 'prependTo()' with 'runValidation=false'.",
        );
    }

    public function testProtectedApplyTreeAttributeConditionRemainsAccessibleToSubclasses(): void
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
    }

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

    public function testChildrenMethodRequiresOrderByForCorrectTreeTraversal(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'Root']);

        $root->makeRoot();

        $childB = new Tree(['name' => 'Child B']);
        $childC = new Tree(['name' => 'Child C']);
        $childA = new Tree(['name' => 'Child A']);

        $childB->appendTo($root);
        $childC->appendTo($root);
        $childA->appendTo($root);

        $command = $this->getDb()->createCommand();

        $command->update('tree', ['lft' => 4, 'rgt' => 5], ['name' => 'Child B'])->execute();
        $command->update('tree', ['lft' => 6, 'rgt' => 7], ['name' => 'Child C'])->execute();
        $command->update('tree', ['lft' => 2, 'rgt' => 3], ['name' => 'Child A'])->execute();
        $command->update('tree', ['rgt' => 8], ['name' => 'Root'])->execute();

        $root->refresh();
        $childrenList = $root->children()->all();

        $expectedOrder = ['Child A', 'Child B', 'Child C'];

        self::assertCount(
            3,
            $childrenList,
            "Children list should contain exactly '3' elements.",
        );

        foreach ($childrenList as $index => $child) {
            self::assertInstanceOf(
                Tree::class,
                $child,
                "Child at index {$index} should be an instance of 'Tree'.",
            );

            if (isset($expectedOrder[$index])) {
                self::assertEquals(
                    $expectedOrder[$index],
                    $child->getAttribute('name'),
                    "Child at index {$index} should be {$expectedOrder[$index]} in correct 'lft' order.",
                );
            }
        }
    }

    public function testMakeRootRefreshIsNecessaryForCorrectAttributeValues(): void
    {
        $this->createDatabase();

        $root = new MultipleTree(['name' => 'Original Root']);

        $root->makeRoot();

        $child1 = new MultipleTree(['name' => 'Child 1']);

        $child1->appendTo($root);

        $child2 = new MultipleTree(['name' => 'Child 2']);

        $child2->appendTo($root);

        $grandchild = new MultipleTree(['name' => 'Grandchild']);

        $grandchild->appendTo($child1);

        $nodeToPromote = MultipleTree::findOne($child1->id);

        self::assertNotNull(
            $nodeToPromote,
            'Child node should exist before promoting to root.',
        );
        self::assertFalse(
            $nodeToPromote->isRoot(),
            "Node should not be root before 'makeRoot()' operation.",
        );

        $originalLeft = $nodeToPromote->getAttribute('lft');
        $originalRight = $nodeToPromote->getAttribute('rgt');
        $originalDepth = $nodeToPromote->getAttribute('depth');
        $originalTree = $nodeToPromote->getAttribute('tree');

        $result = $nodeToPromote->makeRoot();

        self::assertTrue(
            $result,
            "'makeRoot()' should return 'true' when converting node to root.",
        );
        self::assertTrue(
            $nodeToPromote->isRoot(),
            "Node should be identified as root after 'makeRoot()' - this requires 'refresh()' to work.",
        );
        self::assertEquals(
            1,
            $nodeToPromote->getAttribute('lft'),
            "Root node left value should be '1' after 'makeRoot()' - requires 'refresh()' to see updated value.",
        );
        self::assertEquals(
            4,
            $nodeToPromote->getAttribute('rgt'),
            "Root node right value should be '4' after 'makeRoot()' - requires 'refresh()' to see updated value.",
        );
        self::assertEquals(
            0,
            $nodeToPromote->getAttribute('depth'),
            "Root node depth should be '0' after 'makeRoot()' - requires 'refresh()' to see updated value.",
        );
        self::assertEquals(
            $nodeToPromote->getAttribute('id'),
            $nodeToPromote->getAttribute('tree'),
            "Tree attribute should equal node ID for new root - requires 'refresh()' to see updated value.",
        );
        self::assertNotEquals(
            $originalLeft,
            $nodeToPromote->getAttribute('lft'),
            "Left value should have changed from original after 'makeRoot()'.",
        );
        self::assertNotEquals(
            $originalRight,
            $nodeToPromote->getAttribute('rgt'),
            "Right value should have changed from original after 'makeRoot()'.",
        );
        self::assertNotEquals(
            $originalDepth,
            $nodeToPromote->getAttribute('depth'),
            "Depth should have changed from original after 'makeRoot()'.",
        );
        self::assertNotEquals(
            $originalTree,
            $nodeToPromote->getAttribute('tree'),
            "Tree should have changed from original after 'makeRoot()'.",
        );

        $grandchildAfter = MultipleTree::findOne($grandchild->id);

        self::assertNotNull(
            $grandchildAfter,
            "'Grandchild' should still exist after parent became root.",
        );
        self::assertEquals(
            $nodeToPromote->getAttribute('tree'),
            $grandchildAfter->getAttribute('tree'),
            "'Grandchild' should be in the same tree as the new root.",
        );
        self::assertEquals(
            1,
            $grandchildAfter->getAttribute('depth'),
            "'Grandchild' depth should be recalculated relative to new root.",
        );

        $reloadedNode = MultipleTree::findOne($nodeToPromote->id);

        self::assertNotNull(
            $reloadedNode,
            "Node should exist in database after 'makeRoot()'.",
        );
        self::assertTrue(
            $reloadedNode->isRoot(),
            'Reloaded node should be root.',
        );
        self::assertEquals(
            1,
            $reloadedNode->getAttribute('lft'),
            "Reloaded node should have 'left=1'.",
        );
        self::assertEquals(
            4,
            $reloadedNode->getAttribute('rgt'),
            "Reloaded node should have 'right=4'.",
        );
        self::assertEquals(
            0,
            $reloadedNode->getAttribute('depth'),
            "Reloaded node should have 'depth=0'.",
        );
    }
}
