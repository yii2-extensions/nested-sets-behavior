<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use LogicException;
use Throwable;
use yii\base\NotSupportedException;
use yii\db\{ActiveRecord, Exception, StaleObjectException};
use yii\helpers\ArrayHelper;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};

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

    public function testReturnAffectedRowsAndUpdateTreeAfterDeleteWithChildrenWhenManualTransactionIsUsed(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(10);

        self::assertNotNull(
            $node,
            'Node with ID \'10\' should exist before attempting to delete with children using manual transaction.',
        );
        self::assertEquals(
            'Node 2.1',
            $node->getAttribute('name'),
            'Node with ID \'10\' should have the name \'Node 2.1\' before deletion.',
        );
        self::assertFalse(
            $node->isTransactional(ActiveRecord::OP_DELETE),
            'Node with ID \'10\' should not use transactional delete (manual transaction expected).',
        );

        $initialCount = (int) Tree::find()->count();
        $toDeleteCount = (int) Tree::find()
            ->andWhere(['>=', 'lft', $node->getAttribute('lft')])
            ->andWhere(['<=', 'rgt', $node->getAttribute('rgt')])
            ->count();

        self::assertEquals(
            3,
            $toDeleteCount,
            'Node \'2.1\' should have itself and 2 children (total \'3\' nodes to delete).',
        );

        $result = (int) $node->deleteWithChildren();

        self::assertEquals(
            $toDeleteCount,
            $result,
            '\'deleteWithChildren()\' should return the number of affected rows equal to the nodes deleted.',
        );

        $finalCount = (int) Tree::find()->count();

        self::assertEquals(
            $initialCount - $toDeleteCount,
            $finalCount,
            'Tree node count after deletion should decrease by the number of deleted nodes.',
        );
        self::assertNull(
            Tree::findOne(10),
            'Node with ID \'10\' should not exist after deletion.',
        );
        self::assertNull(
            Tree::findOne(11),
            'Node with ID \'11\' should not exist after deletion.',
        );
        self::assertNull(
            Tree::findOne(12),
            'Node with ID \'12\' should not exist after deletion.',
        );
        self::assertNotNull(
            Tree::findOne(1),
            'Root node with ID \'1\' should still exist after deleting node \'10\' and its children.',
        );
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
            'Node with ID \'1\' should not use transactional delete when \'beforeDelete()\' returns \'false\'.',
        );

        $result = $node->deleteWithChildren();

        self::assertFalse(
            $result,
            '\'deleteWithChildren()\' should return \'false\' when \'beforeDelete()\' aborts the deletion process.',
        );
    }

    public function testThrowExceptionWhenDeleteWithChildrenThrowsExceptionInTransaction(): void
    {
        $this->createDatabase();

        $node = new Tree(['name' => 'Root']);

        $node->detachBehavior('nestedSetsBehavior');

        self::assertNull(
            $node->getBehavior('nestedSetsBehavior'),
            'Behavior must be detached before testing exception handling.',
        );

        $nestedSetsBehavior = $this->createMock(NestedSetsBehavior::class);
        $nestedSetsBehavior->expects(self::once())
            ->method('deleteWithChildren')
            ->willThrowException(new Exception('Simulated database error during deletion'));

        $node->attachBehavior('nestedSetsBehavior', $nestedSetsBehavior);
        $behavior = $node->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            NestedSetsBehavior::class,
            $behavior,
            'Behavior must be attached to the node before testing exception handling.',
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Simulated database error during deletion');

        $behavior->deleteWithChildren();
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

    public function testDeleteNodeDoesNotAffectNodeWithLeftEqualToDeletedRight(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'Root']);
        $root->makeRoot();

        $node1 = new Tree(['name' => 'Node1']);
        $node1->appendTo($root);

        $child1 = new Tree(['name' => 'Child1']);
        $child1->appendTo($node1);

        $node2 = new Tree(['name' => 'Node2']);
        $node2->appendTo($root);

        $child2 = new Tree(['name' => 'Child2']);
        $child2->appendTo($node2);

        $root->refresh();
        $node1->refresh();
        $child1->refresh();
        $node2->refresh();
        $child2->refresh();

        self::assertEquals(1, $root->lft);
        self::assertEquals(10, $root->rgt);
        self::assertEquals(2, $node1->lft);
        self::assertEquals(5, $node1->rgt);
        self::assertEquals(3, $child1->lft);
        self::assertEquals(4, $child1->rgt);
        self::assertEquals(6, $node2->lft);
        self::assertEquals(9, $node2->rgt);
        self::assertEquals(7, $child2->lft);
        self::assertEquals(8, $child2->rgt);

        $node1->deleteWithChildren();

        $root->refresh();
        $node2->refresh();
        $child2->refresh();

        self::assertEquals(1, $root->lft);
        self::assertEquals(6, $root->rgt);
        self::assertEquals(2, $node2->lft);
        self::assertEquals(5, $node2->rgt);
        self::assertEquals(3, $child2->lft);
        self::assertEquals(4, $child2->rgt);
    }

    public function testShiftBehaviorAfterDeleteWithPreciseBoundary(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'Root']);
        $root->makeRoot();

        $nodes = [];

        for ($i = 1; $i <= 5; $i++) {
            $nodes[$i] = new Tree(['name' => "Node$i"]);
            // @phpstan-ignore-next-line
            $nodes[$i]->appendTo($root);
        }

        foreach ($nodes as $node) {
            $node->refresh();
        }

        $root->refresh();

        // @phpstan-ignore-next-line
        $nodeToDelete = $nodes[2];
        $rightValueBeforeDelete = $nodeToDelete->rgt;

        $valuesBeforeDelete = [];

        foreach ($nodes as $i => $node) {
            if ($i !== 2) {
                $valuesBeforeDelete[$i] = [
                    'lft' => $node->lft,
                    'rgt' => $node->rgt,
                ];
            }
        }

        $nodeToDelete->delete();

        foreach ($nodes as $i => $node) {
            if ($i !== 2) {
                $node->refresh();

                // @phpstan-ignore-next-line
                $oldLft = $valuesBeforeDelete[$i]['lft'];
                // @phpstan-ignore-next-line
                $oldRgt = $valuesBeforeDelete[$i]['rgt'];

                if ($oldLft > $rightValueBeforeDelete) {
                    self::assertEquals(
                        $oldLft - 2,
                        $node->lft,
                        "Node $i left should be shifted when > deleted right",
                    );
                } else {
                    self::assertEquals(
                        $oldLft,
                        $node->lft,
                        "Node $i left should NOT be shifted when <= deleted right",
                    );
                }

                if ($oldRgt > $rightValueBeforeDelete) {
                    self::assertEquals(
                        $oldRgt - 2,
                        $node->rgt,
                        "Node $i right should be shifted when > deleted right",
                    );
                } else {
                    self::assertEquals(
                        $oldRgt,
                        $node->rgt,
                        "Node $i right should NOT be shifted when <= deleted right",
                    );
                }
            }
        }
    }
}
