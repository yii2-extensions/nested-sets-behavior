<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree, TreeWithStrictValidation};
use yii2\extensions\nestedsets\tests\TestCase;

abstract class AbstractNodeInsert extends TestCase
{
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
            "Node with ID '9' must exist before attempting to 'insertAfter()' a node in another tree.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '53' must exist before attempting to 'insertAfter()' it.",
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
            "Node with ID '9' must exist before attempting to 'insertBefore()' a node in another tree.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '53' must exist before attempting to 'insertBefore()' it.",
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
}
