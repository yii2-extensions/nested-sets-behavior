<?php

declare(strict_types=1);

use yii\db\{ActiveQuery, ActiveRecord};
use Yii2\Extensions\NestedSets\{NestedSetsBehavior, NestedSetsQueryBehavior};

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
