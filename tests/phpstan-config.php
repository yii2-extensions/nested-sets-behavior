<?php

declare(strict_types=1);

use yii\db\{ActiveQuery, ActiveRecord};
use yii2\extensions\nestedsets\{NestedSetsBehavior, NestedSetsQueryBehavior};
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, MultipleTreeQuery, Tree, TreeQuery};

return [
    'behaviors' => [
        ActiveRecord::class => [
            NestedSetsBehavior::class,
        ],
        ActiveQuery::class => [
            NestedSetsQueryBehavior::class,
        ],
        MultipleTree::class => [
            NestedSetsBehavior::class,
        ],
        MultipleTreeQuery::class => [
            NestedSetsQueryBehavior::class,
        ],
        Tree::class => [
            NestedSetsBehavior::class,
        ],
        TreeQuery::class => [
            NestedSetsQueryBehavior::class,
        ],
    ],
];
