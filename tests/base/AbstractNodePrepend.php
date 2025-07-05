<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree, TreeWithStrictValidation};
use yii2\extensions\nestedsets\tests\TestCase;

abstract class AbstractNodePrepend extends TestCase
{
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
            "Node with ID '9' must exist before attempting to 'prependTo()' a node in another tree.",
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            "Target node with ID '53' must exist before attempting to 'prependTo()' it.",
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
}
