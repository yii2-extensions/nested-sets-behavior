<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use yii\helpers\ArrayHelper;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};

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
}
