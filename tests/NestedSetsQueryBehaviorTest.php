<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use LogicException;
use yii\helpers\ArrayHelper;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree, TreeQuery};

final class NestedSetsQueryBehaviorTest extends TestCase
{
    public function testReturnLeavesForSingleAndMultipleTreeModels(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves-query.php",
            ArrayHelper::toArray(Tree::find()->leaves()->all()),
            'Should return correct leaf nodes for \'Tree\' model.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves-multiple-tree-query.php",
            ArrayHelper::toArray(MultipleTree::find()->leaves()->all()),
            'Should return correct leaf nodes for \'MultipleTree\' model.',
        );
    }

    public function testReturnRootsForSingleAndMultipleTreeModels(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-roots-query.php",
            ArrayHelper::toArray(Tree::find()->roots()->all()),
            'Should return correct root nodes for \'Tree\' model.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-roots-multiple-tree-query.php",
            ArrayHelper::toArray(MultipleTree::find()->roots()->all()),
            'Should return correct root nodes for \'MultipleTree\' model.',
        );
    }

    public function testThrowLogicExceptionWhenBehaviorIsNotAttachedToOwner(): void
    {
        $behavior = new NestedSetsQueryBehavior();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "owner" property must be set before using the behavior.');

        $behavior->leaves();
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

    public function testRootsMethodRequiresOrderByForCorrectTreeTraversal(): void
    {
        $this->createDatabase();

        $rootA = new MultipleTree(['name' => 'Root A']);

        $rootA->makeRoot();

        $rootC = new MultipleTree(['name' => 'Root C']);

        $rootC->makeRoot();

        $rootB = new MultipleTree(['name' => 'Root B']);

        $rootB->makeRoot();

        $rootD = new MultipleTree(['name' => 'Root D']);

        $rootD->makeRoot();
        $command = $this->getDb()->createCommand();

        $command->update('multiple_tree', ['tree' => 1], ['name' => 'Root A'])->execute();
        $command->update('multiple_tree', ['tree' => 2], ['name' => 'Root B'])->execute();
        $command->update('multiple_tree', ['tree' => 3], ['name' => 'Root C'])->execute();
        $command->update('multiple_tree', ['tree' => 4], ['name' => 'Root D'])->execute();

        $rootsList = MultipleTree::find()->roots()->all();

        $expectedOrder = ['Root A', 'Root B', 'Root C', 'Root D'];

        self::assertCount(
            4,
            $rootsList,
            'Roots list should contain exactly \'4\' elements.',
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
            'Should have exactly \'2\' initial leaf nodes.',
        );

        $command = $this->getDb()->createCommand();

        $command->update('multiple_tree', ['lft' => 3, 'rgt' => 4], ['name' => 'Leaf B'])->execute();
        $command->update('multiple_tree', ['lft' => 5, 'rgt' => 6], ['name' => 'Leaf A'])->execute();
        $command->update('multiple_tree', ['lft' => 1, 'rgt' => 7], ['name' => 'Root'])->execute();

        $leaves = MultipleTree::find()->leaves()->all();

        /** @phpstan-var array<array{name: string, lft: int}> */
        $expectedLeaves = [
            ['name' => 'Leaf B', 'lft' => 3],
            ['name' => 'Leaf A', 'lft' => 5],
        ];

        self::assertCount(
            2,
            $leaves,
            'Should return exactly \'2\' leaf nodes.',
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

    public function testRootsMethodRequiresLeftAttributeOrderingForSingleTreeScenario(): void
    {
        $this->createDatabase();

        $root1 = new MultipleTree(['name' => 'Root 1']);

        $root1->makeRoot();

        $root2 = new MultipleTree(['name' => 'Root 2']);

        $root2->makeRoot();

        $root3 = new MultipleTree(['name' => 'Root 3']);

        $root3->makeRoot();

        $command = $this->getDb()->createCommand();

        $command->update('multiple_tree', ['tree' => 3, 'lft' => 1, 'rgt' => 2], ['name' => 'Root 1'])->execute();
        $command->update('multiple_tree', ['tree' => 1, 'lft' => 1, 'rgt' => 2], ['name' => 'Root 2'])->execute();
        $command->update('multiple_tree', ['tree' => 5, 'lft' => 1, 'rgt' => 2], ['name' => 'Root 3'])->execute();

        $roots = MultipleTree::find()->roots()->all();

        self::assertCount(
            3,
            $roots,
            'Should return exactly \'3\' root nodes.',
        );

        $expectedOrder = ['Root 2', 'Root 1', 'Root 3'];
        $expectedTreeValues = [1, 3, 5];

        foreach ($roots as $index => $root) {
            self::assertInstanceOf(
                MultipleTree::class,
                $root,
                "Root at index {$index} should be an instance of 'MultipleTree'."
            );

            if (isset($expectedOrder[$index])) {
                self::assertEquals(
                    $expectedOrder[$index],
                    $root->getAttribute('name'),
                    "Root at index {$index} should be {$expectedOrder[$index]} when ordered by 'tree' then 'left' " .
                    'attribute.',
                );
                self::assertEquals(
                    $expectedTreeValues[$index],
                    $root->getAttribute('tree'),
                    "Root at index {$index} should have tree value {$expectedTreeValues[$index]}.",
                );
                self::assertEquals(
                    1,
                    $root->getAttribute('lft'),
                    "Root at index {$index} should have left value \'1\' (all roots must have \'lft=1\').",
                );
            }
        }
    }
}
