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

        $command = $this->getDb()->createCommand();

        $command->insert(
            'multiple_tree',
            [
                'id' => 10,
                'name' => 'Root A',
                'lft' => 1,
                'rgt' => 2,
                'tree' => 1,
                'depth' => 0
            ],
        )->execute();
        $command->insert(
            'multiple_tree',
            [
                'id' => 5,
                'name' => 'Root B',
                'lft' => 1,
                'rgt' => 2,
                'tree' => 2,
                'depth' => 0
            ],
        )->execute();
        $command->insert(
            'multiple_tree',
            [
                'id' => 15,
                'name' => 'Root C',
                'lft' => 1,
                'rgt' => 2,
                'tree' => 3,
                'depth' => 0
            ],
        )->execute();

        $rootsList = MultipleTree::find()->roots()->all();

        $expectedOrderById = ['Root B', 'Root A', 'Root C'];

        self::assertCount(3, $rootsList);

        foreach ($rootsList as $index => $root) {
            self::assertInstanceOf(
                MultipleTree::class,
                $root,
                "Root at index {$index} should be an instance of \'MultipleTree\'.",
            );
            if (isset($expectedOrderById[$index])) {
                self::assertEquals(
                    $expectedOrderById[$index],
                    $root->getAttribute('name'),
                    "Root at index {$index} should be {$expectedOrderById[$index]} when ordered by ID.",
                );
            }
        }
    }
}
