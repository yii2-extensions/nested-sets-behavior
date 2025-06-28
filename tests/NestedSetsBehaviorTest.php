<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use LogicException;
use Throwable;
use yii\base\NotSupportedException;
use yii\db\Exception;
use yii\db\StaleObjectException;
use yii\helpers\ArrayHelper;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};

final class NestedSetsBehaviorTest extends TestCase
{
    public function testReturnTrueAndMatchXmlAfterMakeRootNewForTreeAndMultipleTree(): void
    {
        $this->createDatabase();

        $nodeTree = new Tree(['name' => 'Root']);

        self::assertTrue(
            $nodeTree->makeRoot(),
            '\'makeRoot()\' should return \'true\' when creating a new root node in \'Tree\'.',
        );

        $nodeMultipleTree = new MultipleTree(['name' => 'Root 1']);

        self::assertTrue(
            $nodeMultipleTree->makeRoot(),
            '\'makeRoot()\' should return \'true\' when creating the first root node in \'MultipleTree\'.',
        );

        $nodeMultipleTree = new MultipleTree(['name' => 'Root 2']);

        self::assertTrue(
            $nodeMultipleTree->makeRoot(),
            '\'makeRoot()\' should return \'true\' when creating a second root node in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-make-root-new.xml');

        self::assertSame(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'makeRoot()\' must match the expected XML structure.',
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
            'Node with ID \'9\' must exist before calling \'prependTo()\' on it in \'Tree\'.',
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            '\'prependTo()\' should return \'true\' when prepending a new node to node \'9\' in \'Tree\'.',
        );

        $node = new MultipleTree(['name' => 'New node']);

        $childOfNode = MultipleTree::findOne(31);

        self::assertNotNull(
            $childOfNode,
            'Node with ID \'31\' must exist before calling \'prependTo()\' on it in \'MultipleTree\'.',
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            '\'prependTo()\' should return \'true\' when prepending a new node to node \'31\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-prepend-to-new.xml');

        self::assertSame(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'prependTo()\' must match the expected XML structure.',
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
            'Node with ID \'9\' should exist before calling \'insertBefore()\' on it in \'Tree\'.',
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            '\'insertBefore()\' should return \'true\' when inserting a new node before node \'9\' in \'Tree\'.',
        );

        $node = new MultipleTree(['name' => 'New node']);

        $childOfNode = MultipleTree::findOne(31);

        self::assertNotNull(
            $childOfNode,
            'Node with ID \'31\' should exist before calling \'insertBefore()\' on it in \'MultipleTree\'.',
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            '\'insertBefore()\' should return \'true\' when inserting a new node before node \'31\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-insert-before-new.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'insertBefore()\' must match the expected XML structure.',
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
            'Root node with ID \'1\' should exist before calling \'insertBefore()\' on it in \'Tree\'.',
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
            'Node with ID \'9\' must exist before calling \'insertAfter()\' on it in \'Tree\'.',
        );

        self::assertTrue(
            $node->insertAfter($childOfNode),
            '\'insertAfter()\' should return \'true\' when inserting a new node after node \'9\' in \'Tree\'.',
        );

        $node = new MultipleTree(['name' => 'New node']);

        $childOfNode = MultipleTree::findOne(31);

        self::assertNotNull(
            $childOfNode,
            'Node with ID \'31\' must exist before calling \'insertAfter()\' on it in \'MultipleTree\'.',
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            '\'insertAfter()\' should return \'true\' when inserting a new node after node \'31\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-insert-after-new.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'insertAfter()\' must match the expected XML structure.',
        );
    }

    public function testThrowExceptionWhenInsertAfterNewNodeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is new record.');

        $node->insertAfter(new Tree());
    }

    public function testThrowExceptionWhenInsertAfterNewNodeTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);

        $rootNode = Tree::findOne(1);

        self::assertNotNull(
            $rootNode,
            'Root node with ID \'1\' should exist before calling \'insertAfter()\' on it in \'Tree\'.',
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
            'Node with ID \'31\' must exist before calling \'makeRoot()\' on it in \'MultipleTree\'.',
        );

        $node->name = 'Updated node 2';

        self::assertTrue(
            $node->makeRoot(),
            '\'makeRoot()\' should return \'true\' when called on node \'31\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-make-root-exists.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            'Resulting dataset after \'makeRoot()\' must match the expected XML structure for \'MultipleTree\'.',
        );
    }

    public function testThrowExceptionWhenMakeRootOnNonRootNodeWithTreeAttributeFalse(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, 'Node with ID \'9\' should exist before calling \'makeRoot()\' on it in \'Tree\'.');

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
            'Node with ID \'23\' should exist before calling \'makeRoot()\' on it in \'MultipleTree\'.',
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
            'Node with ID \'9\' must exist before calling \'prependTo()\' on another node in \'Tree\'.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(2);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'2\' must exist before calling \'prependTo()\' on it in \'Tree\'.',
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            '\'prependTo()\' should return \'true\' when moving node \'9\' as child of node \'2\' in \'Tree\'.',
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            'Node with ID \'31\' must exist before calling \'prependTo()\' on another node in \'MultipleTree\'.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(24);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'24\' must exist before calling \'prependTo()\' on it in \'MultipleTree\'.',
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            '\'prependTo()\' should return \'true\' when moving node \'31\' as child of node \'24\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-prepend-to-exists-up.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'prependTo()\' must match the expected XML structure.',
        );
    }

    public function testReturnTrueAndMatchXmlAfterPrependToDownForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' should exist before calling \'prependTo()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(16);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'16\' should exist before calling \'prependTo()\' on it.',
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            '\'prependTo()\' should return \'true\' when moving node \'9\' as child of node \'16\' in \'Tree\'.',
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            'Node with ID \'31\' should exist before calling \'prependTo()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(38);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'38\' should exist before calling \'prependTo()\' on it.',
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            '\'prependTo()\' should return \'true\' when moving node \'31\' as child of node \'38\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-prepend-to-exists-down.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'prependTo()\' must match the expected XML structure.',
        );
    }

    public function testReturnTrueAndMatchXmlAfterPrependToMultipleTreeWhenTargetIsInAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' must exist before attempting to prepend to a node in another tree.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'53\' must exist before attempting to prepend to it.',
        );
        self::assertTrue(
            $node->prependTo($childOfNode),
            '\'prependTo()\' should return \'true\' when moving node \'9\' as child of node \'53\' in another \'tree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-prepend-to-exists-another-tree.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            'Resulting dataset after \'prependTo()\' must match the expected XML structure for \'MultipleTree\'.',
        );
    }

    public function testThrowExceptionWhenPrependToTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, 'Node with ID \'9\' must exist before calling \'prependTo()\' on another node.');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->prependTo(new Tree());
    }

    public function testThrowExceptionWhenPrependToTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, 'Node with ID \'9\' should exist before calling \'prependTo()\' on itself.');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node->prependTo($node);
    }

    public function testThrowExceptionWhenPrependToTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' must exist before calling \'prependTo()\' on another node.',
        );

        $childOfNode = Tree::findOne(11);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'11\' must exist before calling \'prependTo()\' on it.',
        );

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
            'Node with ID \'9\' must exist before calling \'appendTo()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(2);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'2\' must exist before calling \'appendTo()\' on it.',
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            '\'appendTo()\' should return \'true\' when moving node \'9\' as child of node \'2\' in \'Tree\'.',
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            'Node with ID \'31\' must exist before calling \'appendTo()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(24);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'24\' must exist before calling \'appendTo()\' on it.',
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            '\'appendTo()\' should return \'true\' when moving node \'31\' as child of node \'24\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-append-to-exists-up.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'appendTo()\' must match the expected XML structure.',
        );
    }

    public function testReturnTrueAndMatchXmlAfterAppendToDownForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' should exist before calling \'appendTo()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(16);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'16\' should exist before calling \'appendTo()\' on it.',
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            '\'appendTo()\' should return \'true\' when moving node \'9\' as child of node \'16\' in \'Tree\'.',
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            'Node with ID \'31\' should exist before calling \'appendTo()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(38);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'38\' should exist before calling \'appendTo()\' on it.',
        );
        self::assertTrue(
            $node->appendTo($childOfNode),
            '\'appendTo()\' should return \'true\' when moving node \'31\' as child of node \'38\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-append-to-exists-down.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'appendTo()\' must match the expected XML structure.',
        );
    }

    public function testReturnTrueAndMatchXmlAfterAppendToMultipleTreeWhenTargetIsInAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' must exist before attempting to append to a node in another tree.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'53\' must exist before attempting to append to it.',
        );

        self::assertTrue(
            $node->appendTo($childOfNode),
            '\'appendTo()\' should return \'true\' when moving node \'9\' as child of node \'53\' in another tree.',
        );

        $simpleXML = $this->loadFixtureXML('test-append-to-exists-another-tree.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            'Resulting dataset after \'appendTo()\' must match the expected XML structure for \'MultipleTree\'.',
        );
    }

    public function testThrowExceptionWhenAppendToTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, 'Node with ID \'9\' must exist before calling \'appendTo()\' on another node.');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->appendTo(new Tree());
    }

    public function testThrowExceptionWhenAppendToTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' should exist before calling \'appendTo()\' on another node.',
        );

        $childOfNode = Tree::findOne(9);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'9\' should exist before calling \'appendTo()\' on it.',
        );

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
            'Expected node with ID \'9\' to exist before calling \'appendTo()\' on another node.',
        );

        $childOfNode = Tree::findOne(11);

        self::assertNotNull(
            $childOfNode,
            'Expected target child node with ID \'11\' to exist before calling \'appendTo()\' on it.',
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
            'Node with ID \'9\' must exist before calling insertBefore() on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(2);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'2\' must exist before calling insertBefore() on it.',
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            'insertBefore() should return true when moving node \'9\' before node \'2\' in Tree.',
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            'Node with ID \'31\' must exist before calling insertBefore() on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(24);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'24\' must exist before calling insertBefore() on it.',
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            'insertBefore() should return true when moving node \'31\' before node \'24\' in MultipleTree.',
        );

        $simpleXML = $this->loadFixtureXML('test-insert-before-exists-up.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after insertBefore() must match the expected XML structure.',
        );
    }

    public function testReturnTrueAndMatchXmlAfterInsertBeforeDownForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' should exist before calling insertBefore() on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(16);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'16\' should exist before calling insertBefore() on it.',
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            'insertBefore() should return true when moving node \'9\' before node \'16\' in Tree.',
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            'Node with ID \'31\' should exist before calling insertBefore() on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(38);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'38\' should exist before calling insertBefore() on it.',
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            'insertBefore() should return true when moving node \'31\' before node \'38\' in MultipleTree.',
        );

        $simpleXML = $this->loadFixtureXML('test-insert-before-exists-down.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after insertBefore() must match the expected XML structure.',
        );
    }

    public function testReturnTrueAndMatchXmlAfterInsertBeforeMultipleTreeWhenTargetIsInAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' must exist before attempting to insert before a node in another tree.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'53\' must exist before attempting to insert before it.',
        );
        self::assertTrue(
            $node->insertBefore($childOfNode),
            '\'insertBefore()\' should return \'true\' when moving node \'9\' before node \'53\' in another tree.',
        );

        $simpleXML = $this->loadFixtureXML('test-insert-before-exists-another-tree.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            'Resulting dataset after \'insertBefore()\' must match the expected XML structure for \'MultipleTree\'.',
        );
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' should exist before calling \'insertBefore()\' on a new record.',
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->insertBefore(new Tree());
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, 'Node with ID \'9\' should exist before calling \'insertBefore()\' on itself.');

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
            'Node with ID \'9\' must exist before calling \'insertBefore()\' on another node.',
        );

        $childOfNode = Tree::findOne(11);

        self::assertNotNull(
            $childOfNode,
            'Target child node with ID \'11\' must exist before calling \'insertBefore()\' on it.',
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node->insertBefore($childOfNode);
    }

    public function testThrowExceptionWhenInsertBeforeTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' should exist before calling \'insertBefore()\' on another node.',
        );

        $rootNode = Tree::findOne(1);

        self::assertNotNull(
            $rootNode,
            'Root node with ID \'1\' should exist before calling \'insertBefore()\' on it.',
        );

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
            'Node with ID \'9\' must exist before calling \'insertAfter()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(2);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'2\' must exist before calling \'insertAfter()\' on it.',
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            '\'insertAfter()\' should return \'true\' when moving node \'9\' after node \'2\' in \'Tree\'.',
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            'Node with ID \'31\' must exist before calling \'insertAfter()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(24);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'24\' must exist before calling \'insertAfter()\' on it.',
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            '\'insertAfter()\' should return \'true\' when moving node \'31\' after node \'24\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-insert-after-exists-up.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'insertAfter()\' must match the expected XML structure.',
        );
    }

    public function testReturnTrueAndMatchXmlAfterInsertAfterDownForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' should exist before calling \'insertAfter()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = Tree::findOne(16);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'16\' should exist before calling \'insertAfter()\' on it.',
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            '\'insertAfter()\' should return \'true\' when moving node \'9\' after node \'16\' in Tree.',
        );

        $node = MultipleTree::findOne(31);

        self::assertNotNull(
            $node,
            'Node with ID \'31\' should exist before calling \'insertAfter()\' on another node.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(38);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'38\' should exist before calling \'insertAfter()\' on it.',
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            '\'insertAfter()\' should return \'true\' when moving node \'31\' after node \'38\' in \'MultipleTree\'.',
        );

        $simpleXML = $this->loadFixtureXML('test-insert-after-exists-down.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'Resulting dataset after \'insertAfter()\' must match the expected XML structure.',
        );
    }

    public function testReturnTrueAndMatchXmlAfterInsertAfterMultipleTreeWhenTargetIsInAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' must exist before attempting to insert after a node in another tree.',
        );

        $node->name = 'Updated node 2';

        $childOfNode = MultipleTree::findOne(53);

        self::assertNotNull(
            $childOfNode,
            'Target node with ID \'53\' must exist before attempting to insert after it.',
        );
        self::assertTrue(
            $node->insertAfter($childOfNode),
            '\'insertAfter()\' should return \'true\' when moving node \'9\' after node \'53\' in another tree.',
        );

        $simpleXML = $this->loadFixtureXML('test-insert-after-exists-another-tree.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            $simpleXML->asXML(),
            'Resulting dataset after \'insertAfter()\' must match the expected XML structure for \'MultipleTree\'.',
        );
    }

    public function testThrowExceptionWhenInsertAfterTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, 'Node with ID \'9\' must exist before attempting to insert after a new record.');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node->insertAfter(new Tree());
    }

    public function testThrowExceptionWhenInsertAfterTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' must exist before attempting to insert after itself.',
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node->insertAfter($node);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull(
            $node,
            'Node with ID \'9\' must exist before attempting to insert after its child node.',
        );

        $childOfNode = Tree::findOne(11);

        self::assertNotNull(
            $childOfNode,
            'Child node with ID \'11\' must exist before attempting to insert after it.',
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node->insertAfter($childOfNode);
    }

    public function testThrowExceptionWhenInsertAfterTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, 'Node with ID \'9\' should exist before attempting to insert after the root node.');

        $rootNode = Tree::findOne(1);

        self::assertNotNull($rootNode, 'Root node with ID \'1\' should exist before attempting to insert after it.');

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
            'Deleting node with ID \'9\' and its children from \'Tree\' should affect exactly seven rows.',
        );
        self::assertEquals(
            7,
            MultipleTree::findOne(31)?->deleteWithChildren(),
            'Deleting node with ID \'31\' and its children from \'MultipleTree\' should affect exactly seven rows.',
        );

        $simpleXML = $this->loadFixtureXML('test-delete-with-children.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'The XML dataset after deleting nodes with children should match the expected result.',
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
            'Deleting node with ID \'9\' from \'Tree\' should affect exactly one row.',
        );
        self::assertEquals(
            1,
            MultipleTree::findOne(31)?->delete(),
            'Deleting node with ID \'31\' from \'MultipleTree\' should affect exactly one row.',
        );

        $simpleXML = $this->loadFixtureXML('test-delete.xml');

        self::assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            $simpleXML->asXML(),
            'The XML dataset after deleting nodes should match the expected result.',
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
            'Node with ID \'1\' should exist before attempting deletion.',
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

        self::assertNotNull($node, 'Node with ID \'9\' should exist before attempting update.');

        $node->name = 'Updated node';

        self::assertEquals(1, $node->update(), 'Updating the node name should affect exactly one row.');
    }

    public function testReturnParentsForTreeAndMultipleTreeWithAndWithoutDepth(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-parents.php",
            ArrayHelper::toArray(Tree::findOne(11)?->parents()->all() ?? []),
            'Parents for \'Tree\' node with ID \'11\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-parents-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(33)?->parents()->all() ?? []),
            'Parents for \'MultipleTree\' node with ID \'33\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-parents-with-depth.php",
            ArrayHelper::toArray(Tree::findOne(11)?->parents(1)->all() ?? []),
            'Parents with \'depth=1\' for \'Tree\' node with ID \'11\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-parents-multiple-tree-with-depth.php",
            ArrayHelper::toArray(MultipleTree::findOne(33)?->parents(1)->all() ?? []),
            'Parents with \'depth=1\' for \'MultipleTree\' node with ID \'33\' do not match the expected result.',
        );
    }

    public function testReturnChildrenForTreeAndMultipleTreeWithAndWithoutDepth(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-children.php",
            ArrayHelper::toArray(Tree::findOne(9)?->children()->all() ?? []),
            'Children for \'Tree\' node with ID \'9\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-children-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->children()->all() ?? []),
            'Children for \'MultipleTree\' node with ID \'31\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-children-with-depth.php",
            ArrayHelper::toArray(Tree::findOne(9)?->children(1)->all() ?? []),
            'Children with \'depth=1\' for \'Tree\' node with ID \'9\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-children-multiple-tree-with-depth.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->children(1)->all() ?? []),
            'Children with \'depth=1\' for \'MultipleTree\' node with ID \'31\' do not match the expected result.',
        );
    }

    public function testReturnLeavesForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves.php",
            ArrayHelper::toArray(Tree::findOne(9)?->leaves()->all() ?? []),
            'Leaves for \'Tree\' node with ID \'9\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->leaves()->all() ?? []),
            'Leaves for \'MultipleTree\' node with ID \'31\' do not match the expected result.',
        );
    }

    public function testReturnPrevNodesForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-prev.php",
            ArrayHelper::toArray(Tree::findOne(9)?->prev()->all() ?? []),
            'Previous nodes for \'Tree\' node with ID \'9\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-prev-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->prev()->all() ?? []),
            'Previous nodes for \'MultipleTree\' node with ID \'31\' do not match the expected result.',
        );
    }

    public function testReturnNextNodesForTreeAndMultipleTree(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-next.php",
            ArrayHelper::toArray(Tree::findOne(9)?->next()->all() ?? []),
            'Next nodes for \'Tree\' node with ID \'9\' do not match the expected result.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-next-multiple-tree.php",
            ArrayHelper::toArray(MultipleTree::findOne(31)?->next()->all() ?? []),
            'Next nodes for \'MultipleTree\' node with ID \'31\' do not match the expected result.',
        );
    }

    public function testReturnIsRootForRootAndNonRootNode(): void
    {
        $this->generateFixtureTree();

        self::assertTrue(Tree::findOne(1)?->isRoot(), 'Node with ID \'1\' should be identified as root.');
        self::assertFalse(Tree::findOne(2)?->isRoot(), 'Node with ID \'2\' should not be identified as root.');
    }

    public function testReturnIsChildOfForMultipleTreeNodeUnderVariousAncestors(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(26);

        self::assertNotNull(
            $node,
            'Node with ID \'26\' should exist in the database.',
        );
        self::assertNotNull(
            $childOfNode = MultipleTree::findOne(25),
            'Node with ID \'25\' should exist in the database.',
        );
        self::assertTrue(
            $node->isChildOf($childOfNode),
            'Node with ID \'26\' should be a child of node with ID \'25\'.',
        );
        self::assertNotNull(
            $childOfNode = MultipleTree::findOne(23),
            'Node with ID \'23\' should exist in the database.',
        );
        self::assertTrue(
            $node->isChildOf($childOfNode),
            'Node with ID \'26\' should be a child of node with ID \'23\'.',
        );
        self::assertNotNull(
            $childOfNode = MultipleTree::findOne(3),
            'Node with ID \'3\' should exist in the database.',
        );
        self::assertFalse(
            $node->isChildOf($childOfNode),
            'Node with ID \'26\' should not be a child of node with ID \'3\'.',
        );
        self::assertNotNull(
            $childOfNode = MultipleTree::findOne(1),
            'Node with ID \'1\' should exist in the database.',
        );
        self::assertFalse(
            $node->isChildOf($childOfNode),
            'Node with ID \'26\' should not be a child of node with ID \'1\'.',
        );
    }

    public function testIsLeafReturnsTrueForLeafAndFalseForRoot(): void
    {
        $this->generateFixtureTree();

        self::assertTrue(
            Tree::findOne(4)?->isLeaf(),
            'Node with ID \'4\' should be a leaf node (no children).',
        );
        self::assertFalse(
            Tree::findOne(1)?->isLeaf(),
            'Node with ID \'1\' should not be a leaf node (has children or is root).',
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
}
