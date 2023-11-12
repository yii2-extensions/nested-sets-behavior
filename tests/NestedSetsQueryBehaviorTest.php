<?php

declare(strict_types=1);

/**
 * @link https://github.com/creocoder/yii2-nested-sets
 *
 * @copyright Copyright (c) 2015 Alexander Kochetov
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace yii\behavior\nested\sets\tests;

use yii\behavior\nested\sets\tests\models\MultipleTree;
use yii\behavior\nested\sets\tests\models\Tree;
use yii\helpers\ArrayHelper;

final class NestedSetsQueryBehaviorTest extends TestCase
{
    public function testRoots(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/data/test-roots-query.php'),
            ArrayHelper::toArray(Tree::find()->roots()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/data/test-roots-multiple-tree-query.php'),
            ArrayHelper::toArray(MultipleTree::find()->roots()->all())
        );
    }

    public function testLeaves(): void
    {
        $this->generateFixtureTree();

        $this->assertEquals(
            require(__DIR__ . '/data/test-leaves-query.php'),
            ArrayHelper::toArray(Tree::find()->leaves()->all())
        );

        $this->assertEquals(
            require(__DIR__ . '/data/test-leaves-multiple-tree-query.php'),
            ArrayHelper::toArray(MultipleTree::find()->leaves()->all())
        );
    }
}
