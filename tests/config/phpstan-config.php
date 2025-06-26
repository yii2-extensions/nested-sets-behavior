<?php

declare(strict_types=1);

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use Yii2\Extensions\NestedSets\NestedSetsBehavior;
use Yii2\Extensions\NestedSets\NestedSetsQueryBehavior;

return [
    'behaviors' => [
        ActiveRecord::class => [
            NestedSetsBehavior::class,
        ],
        ActiveQuery::class => [
            NestedSetsQueryBehavior::class,
        ],
    ],
];
