<?php

declare(strict_types=1);

use yii\db\{ActiveQuery, ActiveRecord};
use yii2\extensions\nestedsets\{NestedSetsBehavior, NestedSetsQueryBehavior};
use yii2\extensions\nestedsets\tests\support\model\{
    ExtendableMultipleTree,
    MultipleTree,
    MultipleTreeQuery,
    Tree,
    TreeQuery,
    TreeWithStrictValidation,
};

return [
    'behaviors' => [
        ActiveRecord::class => [
            NestedSetsBehavior::class,
        ],
        ActiveQuery::class => [
            NestedSetsQueryBehavior::class,
        ],
        ExtendableMultipleTree::class => [
            NestedSetsBehavior::class,
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
        TreeWithStrictValidation::class => [
            NestedSetsBehavior::class,
        ],
    ],
];
