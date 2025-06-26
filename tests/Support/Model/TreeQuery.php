<?php

declare(strict_types=1);

namespace Yii2\Extensions\NestedSets\Tests\Support\Model;

use Yii2\Extensions\NestedSets\NestedSetsQueryBehavior;

class TreeQuery extends \yii\db\ActiveQuery
{
    public function behaviors()
    {
        return [
            NestedSetsQueryBehavior::class,
        ];
    }
}
