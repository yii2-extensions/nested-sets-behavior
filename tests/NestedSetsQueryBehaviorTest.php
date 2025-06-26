<?php

declare(strict_types=1);

namespace Yii2\Extensions\NestedSets\Tests;

use yii\helpers\ArrayHelper;
use Yii2\Extensions\NestedSets\Tests\Support\Model\MultipleTree;
use Yii2\Extensions\NestedSets\Tests\Support\Model\Tree;

final class NestedSetsQueryBehaviorTest extends TestCase
{
    public function testRoots(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-roots-query.php'),
            ArrayHelper::toArray(Tree::find()->roots()->all()),
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-roots-multiple-tree-query.php'),
            ArrayHelper::toArray(MultipleTree::find()->roots()->all()),
        );
    }

    public function testLeaves(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-leaves-query.php'),
            ArrayHelper::toArray(Tree::find()->leaves()->all()),
        );

        $this->assertEquals(
            require(__DIR__ . '/Support/data/test-leaves-multiple-tree-query.php'),
            ArrayHelper::toArray(MultipleTree::find()->leaves()->all()),
        );
    }
}
