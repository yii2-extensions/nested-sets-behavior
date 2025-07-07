<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use yii\helpers\ArrayHelper;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Base class for tree traversal and relationship tests in nested sets tree behaviors.
 *
 * Provides a suite of unit tests for verifying traversal methods, node ordering, and parent/child/leaf relationships in
 * both single-tree and multi-tree nested sets models.
 *
 * This class ensures the correctness and determinism of methods that retrieve children, leaves, parents, next, and
 * previous nodes, including order-by requirements and depth constraints, by testing various tree structures and update
 * scenarios.
 *
 * Key features.
 * - Comparison of actual results with expected fixtures for all traversal methods.
 * - Coverage for both {@see Tree} and {@see MultipleTree} model implementations.
 * - Ensures correct node ordering and deterministic traversal for children, leaves, and parents.
 * - Tests for order-by enforcement in traversal queries.
 * - Validation of depth constraints and structure updates.
 *
 * @see MultipleTree for multi-tree model.
 * @see Tree for single-tree model.
 *
 * @phpstan-type NodeChildren array<string|array{name: string, children?: array<mixed>}>
 * @phpstan-type TreeStructure array<array<mixed>>
 * @phpstan-type UpdateData array<array{name: string, lft?: int, rgt?: int, depth?: int}>
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
abstract class AbstractTreeTraversal extends TestCase
{
    public function testChildrenMethodRequiresOrderByForCorrectTreeTraversal(): void
    {
        $expectedOrder = ['Child A', 'Child B', 'Child C'];

        $treeStructure = [
            ['name' => 'Root', 'children' => ['Child B', 'Child C', 'Child A']],
        ];

        $updates = [
            ['name' => 'Child B', 'lft' => 4, 'rgt' => 5],
            ['name' => 'Child C', 'lft' => 6, 'rgt' => 7],
            ['name' => 'Child A', 'lft' => 2, 'rgt' => 3],
            ['name' => 'Root', 'rgt' => 8],
        ];

        $tree = $this->createTreeStructure($treeStructure, $updates);
        $nodeList = $tree->children()->all();

        $this->assertNodesInCorrectOrder($nodeList, $expectedOrder, 'Child');
    }

    public function testLeavesMethodRequiresOrderByForDeterministicResults(): void
    {
        $expectedOrder = ['Leaf A', 'Leaf B', 'Leaf C'];

        $treeStructure = [
            ['name' => 'Root', 'children' => ['Leaf A', 'Leaf B', 'Leaf C']],
        ];

        $updates = [
            ['name' => 'Leaf C', 'lft' => 6, 'rgt' => 7],
            ['name' => 'Leaf B', 'lft' => 4, 'rgt' => 5],
            ['name' => 'Leaf A', 'lft' => 2, 'rgt' => 3],
            ['name' => 'Root', 'rgt' => 8],
        ];

        $tree = $this->createTreeStructure($treeStructure, $updates);
        $treeQuery = $tree->leaves();

        $this->assertQueryHasOrderBy($treeQuery, 'leaves()');

        $leaves = $treeQuery->all();

        $this->assertNodesInCorrectOrder($leaves, $expectedOrder, 'Leaf');
    }

    public function testParentsMethodRequiresOrderByForDeterministicResults(): void
    {
        $treeStructure = [
            [
                'name' => 'Root A',
                'children' => [
                    [
                        'name' => 'Parent B',
                        'children' => [
                            [
                                'name' => 'Parent C',
                                'children' => ['Child'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $updates = [
            ['name' => 'Parent C', 'lft' => 4, 'rgt' => 7, 'depth' => 2],
            ['name' => 'Parent B', 'lft' => 2, 'rgt' => 8, 'depth' => 1],
            ['name' => 'Root A', 'lft' => 1, 'rgt' => 9, 'depth' => 0],
            ['name' => 'Child', 'lft' => 5, 'rgt' => 6, 'depth' => 3],
        ];

        $this->createTreeStructure($treeStructure, $updates);

        $tree = Tree::findOne(['name' => 'Child']);

        self::assertNotNull(
            $tree,
            "Child node should exist in the database with name 'Child'.",
        );

        $treeQuery = $tree->parents();

        $this->assertQueryHasOrderBy($treeQuery, 'parents()');

        $parents = $treeQuery->all();

        $this->assertNodesInCorrectOrder($parents, ['Root A', 'Parent B', 'Parent C'], 'Parent');
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
}
