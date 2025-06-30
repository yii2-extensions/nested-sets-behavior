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
                "Root at index {$index} should be an instance of \'MultipleTree\'.",
            );
            if (isset($expectedOrder[$index])) {
                self::assertEquals(
                    $expectedOrder[$index],
                    $root->getAttribute('name'),
                    "Root at index {$index} should be {$expectedOrder[$index]} in correct \'tree\' order.",
                );
            }
        }
    }
}
