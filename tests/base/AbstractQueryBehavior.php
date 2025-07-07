<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use LogicException;
use yii\db\Exception;
use yii\helpers\ArrayHelper;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree, TreeQuery};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Base class for query behavior tests in nested sets tree behaviors.
 *
 * Provides a suite of unit tests for verifying query methods related to leaf and root node retrieval, ordering, and
 * behavior attachment in both single-tree and multi-tree nested sets models.
 *
 * This class ensures the correctness of query methods such as `leaves()` and `roots()`, including their ordering
 * guarantees, SQL generation, and error handling when the behavior is detached or not attached to the owner.
 *
 * Key features.
 * - Ensures deterministic ordering of results by left attribute and tree attribute.
 * - Tests for correct leaf and root node retrieval in {@see Tree} and {@see MultipleTree} models.
 * - Validates SQL query structure for ordering requirements.
 * - Verifies exception handling when the behavior is detached or not attached to the query owner.
 *
 * @see MultipleTree for multi-tree model.
 * @see NestedSetsQueryBehavior for query behavior implementation.
 * @see Tree for single-tree model.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
abstract class AbstractQueryBehavior extends TestCase
{
    /**
     * @throws Exception if an unexpected error occurs during execution.
     */
    public function testLeavesMethodRequiresLeftAttributeOrderingForConsistentResults(): void
    {
        $this->createDatabase();

        $root = new MultipleTree(['name' => 'Root']);

        $root->makeRoot();

        $leaf1 = new MultipleTree(['name' => 'Leaf A']);

        $leaf1->appendTo($root);

        $leaf2 = new MultipleTree(['name' => 'Leaf B']);

        $leaf2->appendTo($root);

        $initialLeaves = MultipleTree::find()->leaves()->all();

        self::assertCount(
            2,
            $initialLeaves,
            "Should have exactly '2' initial leaf nodes.",
        );

        $command = $this->getDb()->createCommand();

        $command->update('multiple_tree', ['lft' => 3, 'rgt' => 4], ['name' => 'Leaf B'])->execute();
        $command->update('multiple_tree', ['lft' => 5, 'rgt' => 6], ['name' => 'Leaf A'])->execute();
        $command->update('multiple_tree', ['lft' => 1, 'rgt' => 7], ['name' => 'Root'])->execute();

        $leaves = MultipleTree::find()->leaves()->all();

        /** @phpstan-var array<array{name: string, lft: int}> $expectedLeaves */
        $expectedLeaves = [
            ['name' => 'Leaf B', 'lft' => 3],
            ['name' => 'Leaf A', 'lft' => 5],
        ];

        self::assertCount(
            2,
            $leaves,
            "Should return exactly '2' leaf nodes.",
        );

        foreach ($leaves as $index => $leaf) {
            self::assertInstanceOf(
                MultipleTree::class,
                $leaf,
                "Leaf at index {$index} should be an instance of 'MultipleTree'.",
            );

            if (isset($expectedLeaves[$index])) {
                self::assertEquals(
                    $expectedLeaves[$index]['name'],
                    $leaf->getAttribute('name'),
                    "Leaf at index {$index} should be {$expectedLeaves[$index]['name']} in correct order.",
                );
                self::assertEquals(
                    $expectedLeaves[$index]['lft'],
                    $leaf->getAttribute('lft'),
                    "Leaf at index {$index} should have left value {$expectedLeaves[$index]['lft']}.",
                );
            }
        }
    }

    public function testReturnLeavesForSingleAndMultipleTreeModels(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves-query.php",
            ArrayHelper::toArray(Tree::find()->leaves()->all()),
            "Should return correct leaf nodes for 'Tree' model.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves-multiple-tree-query.php",
            ArrayHelper::toArray(MultipleTree::find()->leaves()->all()),
            "Should return correct leaf nodes for 'MultipleTree' model.",
        );
    }

    public function testReturnRootsForSingleAndMultipleTreeModels(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-roots-query.php",
            ArrayHelper::toArray(Tree::find()->roots()->all()),
            "Should return correct root nodes for 'Tree' model.",
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-roots-multiple-tree-query.php",
            ArrayHelper::toArray(MultipleTree::find()->roots()->all()),
            "Should return correct root nodes for 'MultipleTree' model.",
        );
    }

    public function testRootsMethodRequiresLeftAttributeOrderingWhenTreeAttributeIsDisabled(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'Root']);

        $root->makeRoot();

        $query = Tree::find()->roots();

        $sql = $query->createCommand()->getRawSql();

        self::assertStringContainsString(
            'ORDER BY',
            $sql,
            "'roots()' query should include 'ORDER BY' clause for consistent results.",
        );
        self::assertStringContainsString(
            $this->replaceQuotes('[[lft]]'),
            $sql,
            "'roots()' query should order by 'left' attribute for deterministic ordering.",
        );

        $roots = $query->all();

        self::assertCount(
            1,
            $roots,
            "Should return exactly '1' root node when 'treeAttribute' is disabled.",
        );

        if (isset($roots[0])) {
            self::assertInstanceOf(
                Tree::class,
                $roots[0],
                "Root node should be an instance of 'Tree'.",
            );
            self::assertEquals(
                'Root',
                $roots[0]->getAttribute('name'),
                'Root should have the correct name.',
            );
            self::assertEquals(
                1,
                $roots[0]->getAttribute('lft'),
                "Root should have left value of '1' indicating it is a root node.",
            );
        }
    }

    /**
     * @throws Exception if an unexpected error occurs during execution.
     */
    public function testRootsMethodRequiresOrderByForCorrectTreeTraversal(): void
    {
        $this->createDatabase();

        $treeIds = [1, 2, 3, 4];
        $rootNames = ['Root A', 'Root C', 'Root B', 'Root D'];
        $expectedOrder = ['Root A', 'Root B', 'Root C', 'Root D'];

        foreach ($rootNames as $name) {
            $root = new MultipleTree(['name' => $name]);

            $root->makeRoot();
        }

        $command = $this->getDb()->createCommand();

        foreach ($expectedOrder as $index => $name) {
            $command->update('multiple_tree', ['tree' => $treeIds[$index]], ['name' => $name])->execute();
        }

        $rootsList = MultipleTree::find()->roots()->all();

        self::assertCount(
            4,
            $rootsList,
            "Roots list should contain exactly '4' elements.",
        );

        foreach ($rootsList as $index => $root) {
            self::assertInstanceOf(
                MultipleTree::class,
                $root,
                "Root at index {$index} should be an instance of 'MultipleTree'.",
            );

            if (isset($expectedOrder[$index])) {
                self::assertEquals(
                    $expectedOrder[$index],
                    $root->getAttribute('name'),
                    "Root at index {$index} should be {$expectedOrder[$index]} in correct 'tree' order.",
                );
            }
        }
    }

    public function testThrowLogicExceptionWhenBehaviorIsDetachedFromOwner(): void
    {
        $this->createDatabase();

        $node = new TreeQuery(Tree::class);

        $behavior = $node->getBehavior('nestedSetsQueryBehavior');

        self::assertInstanceOf(NestedSetsQueryBehavior::class, $behavior);

        $node->detachBehavior('nestedSetsQueryBehavior');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "owner" property must be set before using the behavior.');

        $behavior->leaves();
    }

    public function testThrowLogicExceptionWhenBehaviorIsNotAttachedToOwner(): void
    {
        $behavior = new NestedSetsQueryBehavior();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "owner" property must be set before using the behavior.');

        $behavior->leaves();
    }
}
