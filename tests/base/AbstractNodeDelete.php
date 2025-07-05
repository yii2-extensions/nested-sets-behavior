<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use Throwable;
use yii\db\{ActiveRecord, StaleObjectException};
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};
use yii2\extensions\nestedsets\tests\TestCase;

abstract class AbstractNodeDelete extends TestCase
{
    public function testNodeStateAfterDeleteWithChildren(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'Root']);

        $root->makeRoot();

        $child = new Tree(['name' => 'Child']);

        $child->appendTo($root);

        $grandchild = new Tree(['name' => 'Grandchild']);

        $grandchild->appendTo($child);

        self::assertFalse(
            $child->getIsNewRecord(),
            'Child node should not be marked as new record before deletion.',
        );
        self::assertNotEmpty(
            $child->getOldAttributes(),
            'Child node should have old attributes before deletion.',
        );

        $result = $child->deleteWithChildren();

        self::assertNotFalse(
            $result,
            'DeleteWithChildren should return the number of deleted rows.',
        );
        self::assertTrue(
            $child->getIsNewRecord(),
            "Child node should be marked as new record after deletion ('setOldAttributes(null)' effect).",
        );
        self::assertEmpty(
            $child->getOldAttributes(),
            'Child node should have empty old attributes after deletion.',
        );
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
    public function testReturnOneWhenUpdateNodeName(): void
    {
        $this->generateFixtureTree();

        $node = Tree::findOne(9);

        self::assertNotNull($node, "Node with ID '9' should exist before attempting update.");

        $node->name = 'Updated node';

        self::assertEquals(1, $node->update(), 'Updating the node name should affect exactly one row.');
    }
}
