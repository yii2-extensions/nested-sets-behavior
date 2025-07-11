<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Base class for node state and relationship tests in nested sets tree behaviors.
 *
 * Provides a suite of unit tests for verifying node state, parent-child relationships, and root/leaf detection in both
 * single-tree and multi-tree nested sets models.
 *
 * This class ensures the correctness of methods that determine node ancestry, root status, and leaf status by testing
 * various edge cases and boundary conditions, such as equal left/right values and ancestor chains.
 *
 * Key features.
 * - Coverage for both {@see Tree} and {@see MultipleTree} model implementations.
 * - Ensures correct behavior for left/right value manipulations and ancestor checks.
 * - Tests for `isChildOf()` under different ancestor and boundary scenarios.
 * - Validation of `isRoot()` and `isLeaf()` logic for root, leaf, and intermediate nodes.
 *
 * @see MultipleTree for multi-tree model.
 * @see Tree for single-tree model.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
abstract class AbstractNodeState extends TestCase
{
    public function testIsChildOfReturnsFalseWhenLeftValuesAreEqual(): void
    {
        $this->generateFixtureTree();

        $parentNode = Tree::findOne(2);
        $childNode = Tree::findOne(3);

        self::assertNotNull($parentNode, 'Parent node should exist for boundary testing.');
        self::assertNotNull($childNode, 'Child node should exist for boundary testing.');

        $originalChildLeft = $childNode->getAttribute('lft');

        $parentLeft = $parentNode->getAttribute('lft');
        $childNode->setAttribute('lft', $parentLeft);

        self::assertFalse(
            $childNode->isChildOf($parentNode),
            "Node should not be child when left values are equal ('tests <= condition').",
        );

        $childNode->setAttribute('lft', $originalChildLeft);
    }

    public function testIsChildOfReturnsFalseWhenRightValuesAreEqual(): void
    {
        $this->generateFixtureTree();

        $parentNode = Tree::findOne(2);
        $childNode = Tree::findOne(3);

        self::assertNotNull($parentNode, 'Parent node should exist for boundary testing.');
        self::assertNotNull($childNode, 'Child node should exist for boundary testing.');

        $originalChildRight = $childNode->getAttribute('rgt');

        $parentRight = $parentNode->getAttribute('rgt');
        $childNode->setAttribute('rgt', $parentRight);

        self::assertFalse(
            $childNode->isChildOf($parentNode),
            "Node should not be child when right values are equal ('tests >= condition').",
        );

        $childNode->setAttribute('rgt', $originalChildRight);
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

    public function testReturnIsRootForRootAndNonRootNode(): void
    {
        $this->generateFixtureTree();

        self::assertTrue(Tree::findOne(1)?->isRoot(), "Node with ID '1' should be identified as root.");
        self::assertFalse(Tree::findOne(2)?->isRoot(), "Node with ID '2' should not be identified as root.");
    }
}
