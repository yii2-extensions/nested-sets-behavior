<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use yii\db\Exception;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree, TreeWithStrictValidation};
use yii2\extensions\nestedsets\tests\TestCase;

abstract class AbstractNodeAppend extends TestCase
{
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
}
