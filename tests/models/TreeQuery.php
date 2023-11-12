<?php

declare(strict_types=1);

/**
 * @link https://github.com/creocoder/yii2-nested-sets
 * @copyright Copyright (c) 2015 Alexander Kochetov
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace yii\behavior\nested\sets\tests\models;

use yii\behavior\nested\sets\NestedSetsQueryBehavior;

/**
 * TreeQuery
 */
class TreeQuery extends \yii\db\ActiveQuery
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            NestedSetsQueryBehavior::class,
        ];
    }
}
