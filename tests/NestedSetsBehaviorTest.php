<?php

declare(strict_types=1);

namespace Yii2\Extensions\NestedSets\Tests;

use Yii2\Extensions\NestedSets\Tests\Support\Model\MultipleTree;
use Yii2\Extensions\NestedSets\Tests\Support\Model\Tree;
use yii\base\NotSupportedException;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

final class NestedSetsBehaviorTest extends TestCase
{
    public function testMakeRootNewNew(): void
    {
        $this->createDatabase();

        $nodeTree = new Tree(['name' => 'Root']);
        $this->assertTrue($nodeTree->makeRoot());

        $nodeMultipleTree = new MultipleTree(['name' => 'Root 1']);
        $this->assertTrue($nodeMultipleTree->makeRoot());

        $nodeMultipleTree = new MultipleTree(['name' => 'Root 2']);
        $this->assertTrue($nodeMultipleTree->makeRoot());

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-make-root-new.xml')->asXML(),
        );
    }

    public function testMakeRootNewExceptionIsRaisedWhenTreeAttributeIsFalseAndRootIsExists(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create more than one root when "treeAttribute" is false.');

        $this->generateFixtureTree();

        $node = new Tree(['name' => 'Root']);
        $node->makeRoot();
    }

    public function testPrependToNew(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);
        $this->assertTrue($node->prependTo(Tree::findOne(9)));

        $node = new MultipleTree(['name' => 'New node']);
        $this->assertTrue($node->prependTo(MultipleTree::findOne(31)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-prepend-to-new.xml')->asXML(),
        );
    }

    public function testAppendNewToExceptionIsRaisedWhenTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is new record.');

        $node = new Tree(['name' => 'New node']);
        $node->appendTo(new Tree());
    }

    public function testInsertBeforeNew(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);
        $this->assertTrue($node->insertBefore(Tree::findOne(9)));

        $node = new MultipleTree(['name' => 'New node']);
        $this->assertTrue($node->insertBefore(MultipleTree::findOne(31)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-insert-before-new.xml')->asXML(),
        );
    }

    public function testInsertBeforeNewExceptionIsRaisedWhenTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is new record.');

        $node = new Tree(['name' => 'New node']);
        $node->insertBefore(new Tree());
    }

    public function testInsertBeforeNewExceptionIsRaisedWhenTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is root.');

        $node = new Tree(['name' => 'New node']);
        $node->insertBefore(Tree::findOne(1));
    }

    public function testInsertAfterNew(): void
    {
        $this->generateFixtureTree();

        $node = new Tree(['name' => 'New node']);
        $this->assertTrue($node->insertAfter(Tree::findOne(9)));

        $node = new MultipleTree(['name' => 'New node']);
        $this->assertTrue($node->insertAfter(MultipleTree::findOne(31)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-insert-after-new.xml')->asXML(),
        );
    }

    public function testInsertAfterNewExceptionIsRaisedWhenTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is new record.');

        $node = new Tree(['name' => 'New node']);
        $node->insertAfter(new Tree());
    }

    public function testInsertAfterNewExceptionIsRaisedWhenTargetIsRoot()
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not create a node when the target node is root.');

        $node = new Tree(['name' => 'New node']);
        $node->insertAfter(Tree::findOne(1));
    }

    public function testMakeRootExists()
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->makeRoot());

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            simplexml_load_file(__DIR__ . '/Support/data/test-make-root-exists.xml')->asXML(),
        );
    }

    public function testMakeRootExistsExceptionIsRaisedWhenTreeAttributeIsFalse(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node as the root when "treeAttribute" is false.');

        $node = Tree::findOne(9);
        $node->makeRoot();
    }

    public function testMakeRootExistsExceptionIsRaisedWhenItsRoot(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move the root node as the root.');

        $node = MultipleTree::findOne(23);
        $node->makeRoot();
    }

    public function testPrependToExistsUp(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->prependTo(Tree::findOne(2)));

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->prependTo(MultipleTree::findOne(24)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-prepend-to-exists-up.xml')->asXML(),
        );
    }

    public function testPrependToExistsDown(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->prependTo(Tree::findOne(16)));

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->prependTo(MultipleTree::findOne(38)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-prepend-to-exists-down.xml')->asXML(),
        );
    }

    public function testPrependToExistsAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->prependTo(MultipleTree::findOne(53)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            simplexml_load_file(__DIR__ . '/Support/data/test-prepend-to-exists-another-tree.xml')->asXML(),
        );
    }

    public function testPrependToExistsExceptionIsRaisedWhenTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node = Tree::findOne(9);
        $node->prependTo(new Tree());
    }

    public function testPrependToExistsExceptionIsRaisedWhenTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node = Tree::findOne(9);
        $node->prependTo(Tree::findOne(9));
    }

    public function testPrependToExistsExceptionIsRaisedWhenTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node = Tree::findOne(9);
        $node->prependTo(Tree::findOne(11));
    }

    public function testAppendToExistsUp(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->appendTo(Tree::findOne(2)));

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->appendTo(MultipleTree::findOne(24)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-append-to-exists-up.xml')->asXML(),
        );
    }

    public function testAppendToExistsDown(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->appendTo(Tree::findOne(16)));

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->appendTo(MultipleTree::findOne(38)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-append-to-exists-down.xml')->asXML(),
        );
    }

    public function testAppendToExistsAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->appendTo(MultipleTree::findOne(53)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            simplexml_load_file(__DIR__ . '/Support/data/test-append-to-exists-another-tree.xml')->asXML(),
        );
    }

    public function testAppendToExistsExceptionIsRaisedWhenTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node = Tree::findOne(9);
        $node->appendTo(new Tree());
    }

    public function testAppendToExistsExceptionIsRaisedWhenTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node = Tree::findOne(9);
        $node->appendTo(Tree::findOne(9));
    }

    public function testAppendToExistsExceptionIsRaisedWhenTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node = Tree::findOne(9);
        $node->appendTo(Tree::findOne(11));
    }

    public function testInsertBeforeExistsUp(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertBefore(Tree::findOne(2)));

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertBefore(MultipleTree::findOne(24)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-insert-before-exists-up.xml')->asXML(),
        );
    }

    public function testInsertBeforeExistsDown(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertBefore(Tree::findOne(16)));

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertBefore(MultipleTree::findOne(38)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-insert-before-exists-down.xml')->asXML(),
        );
    }

    public function testInsertBeforeExistsAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertBefore(MultipleTree::findOne(53)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            simplexml_load_file(__DIR__ . '/Support/data/test-insert-before-exists-another-tree.xml')->asXML(),
        );
    }

    public function testInsertBeforeExistsExceptionIsRaisedWhenTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node = Tree::findOne(9);
        $node->insertBefore(new Tree());
    }

    public function testInsertBeforeExistsExceptionIsRaisedWhenTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node = Tree::findOne(9);
        $node->insertBefore(Tree::findOne(9));
    }

    public function testInsertBeforeExistsExceptionIsRaisedWhenTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node = Tree::findOne(9);
        $node->insertBefore(Tree::findOne(11));
    }

    public function testInsertBeforeExistsExceptionIsRaisedWhenTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is root.');

        $node = Tree::findOne(9);
        $node->insertBefore(Tree::findOne(1));
    }

    public function testInsertAfterExistsUp(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertAfter(Tree::findOne(2)));

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertAfter(MultipleTree::findOne(24)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-insert-after-exists-up.xml')->asXML(),
        );
    }

    public function testInsertAfterExistsDown(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertAfter(Tree::findOne(16)));

        $node = MultipleTree::findOne(31);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertAfter(MultipleTree::findOne(38)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-insert-after-exists-down.xml')->asXML(),
        );
    }

    public function testInsertAfterExistsAnotherTree(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertTrue($node->insertAfter(MultipleTree::findOne(53)));

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSetMultipleTree()),
            simplexml_load_file(__DIR__ . '/Support/data/test-insert-after-exists-another-tree.xml')->asXML(),
        );
    }

    public function testInsertAfterExistsExceptionIsRaisedWhenTargetIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is new record.');

        $node = Tree::findOne(9);
        $node->insertAfter(new Tree());
    }

    public function testInsertAfterExistsExceptionIsRaisedWhenTargetIsSame(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $node = Tree::findOne(9);
        $node->insertAfter(Tree::findOne(9));
    }

    public function testInsertAfterExistsExceptionIsRaisedWhenTargetIsChild(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $node = Tree::findOne(9);
        $node->insertAfter(Tree::findOne(11));
    }

    public function testInsertAfterExistsExceptionIsRaisedWhenTargetIsRoot(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is root.');

        $node = Tree::findOne(9);
        $node->insertAfter(Tree::findOne(1));
    }

    public function testDeleteWithChildren(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(7, Tree::findOne(9)->deleteWithChildren());
        $this->assertEquals(7, MultipleTree::findOne(31)->deleteWithChildren());

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-delete-with-children.xml')->asXML(),
        );
    }

    public function testDeleteWithChildrenExceptionIsRaisedWhenNodeIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not delete a node when it is new record.');

        $node = new Tree();
        $node->deleteWithChildren();
    }

    public function testDelete(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(1, Tree::findOne(9)->delete());
        $this->assertEquals(1, MultipleTree::findOne(31)->delete());

        $this->assertEquals(
            $this->buildFlatXMLDataSet($this->getDataSet()),
            simplexml_load_file(__DIR__ . '/Support/data/test-delete.xml')->asXML(),
        );
    }

    public function testDeleteExceptionIsRaisedWhenNodeIsNewRecord(): void
    {
        $this->generateFixtureTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not delete a node when it is new record.');

        $node = new Tree();
        $node->delete();
    }

    public function testDeleteExceptionIsRaisedWhenNodeIsRoot(): void
    {
        $this->generateFixtureTree();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Method "Yii2\Extensions\NestedSets\Tests\Support\Model\Tree::delete" is not supported for deleting root nodes.'
        );

        $node = Tree::findOne(1);
        $node->delete();
    }

    public function testExceptionIsRaisedWhenInsertIsCalled(): void
    {
        $this->generateFixtureTree();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Method "Yii2\Extensions\NestedSets\Tests\Support\Model\Tree::insert" is not supported for inserting new nodes.'
        );

        $node = new Tree(['name' => 'Node']);
        $node->insert();
    }

    public function testUpdate(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);
        $node->name = 'Updated node 2';
        $this->assertEquals(1, $node->update());
    }

    public function testParents(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-parents.php'),
            ArrayHelper::toArray(Tree::findOne(11)->parents()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-parents-multiple-tree.php'),
            ArrayHelper::toArray(MultipleTree::findOne(33)->parents()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-parents-with-depth.php'),
            ArrayHelper::toArray(Tree::findOne(11)->parents(1)->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-parents-multiple-tree-with-depth.php'),
            ArrayHelper::toArray(MultipleTree::findOne(33)->parents(1)->all())
        );
    }

    public function testChildren(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-children.php'),
            ArrayHelper::toArray(Tree::findOne(9)->children()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-children-multiple-tree.php'),
            ArrayHelper::toArray(MultipleTree::findOne(31)->children()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-children-with-depth.php'),
            ArrayHelper::toArray(Tree::findOne(9)->children(1)->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-children-multiple-tree-with-depth.php'),
            ArrayHelper::toArray(MultipleTree::findOne(31)->children(1)->all())
        );
    }

    public function testLeaves(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-leaves.php'),
            ArrayHelper::toArray(Tree::findOne(9)->leaves()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-leaves-multiple-tree.php'),
            ArrayHelper::toArray(MultipleTree::findOne(31)->leaves()->all())
        );
    }

    public function testPrev(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-prev.php'),
            ArrayHelper::toArray(Tree::findOne(9)->prev()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-prev-multiple-tree.php'),
            ArrayHelper::toArray(MultipleTree::findOne(31)->prev()->all())
        );
    }

    public function testNext(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-next.php'),
            ArrayHelper::toArray(Tree::findOne(9)->next()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-next-multiple-tree.php'),
            ArrayHelper::toArray(MultipleTree::findOne(31)->next()->all())
        );
    }

    public function testIsRoot(): void
    {
        $this->generateFixtureTree();

        $this->assertTrue(Tree::findOne(1)->isRoot());
        $this->assertFalse(Tree::findOne(2)->isRoot());
    }

    public function testIsChildOf(): void
    {
        $this->generateFixtureTree();

        $node = MultipleTree::findOne(26);

        $this->assertTrue($node->isChildOf(MultipleTree::findOne(25)));
        $this->assertTrue($node->isChildOf(MultipleTree::findOne(23)));
        $this->assertFalse($node->isChildOf(MultipleTree::findOne(3)));
        $this->assertFalse($node->isChildOf(MultipleTree::findOne(1)));
    }

    public function testIsLeaf(): void
    {
        $this->generateFixtureTree();

        $this->assertTrue(Tree::findOne(4)->isLeaf());
        $this->assertFalse(Tree::findOne(1)->isLeaf());
    }
}
