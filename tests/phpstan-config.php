<?php

declare(strict_types=1);

use yii\console\Application;
use yii\db\{ActiveQuery, ActiveRecord};
use yii2\extensions\nestedsets\{NestedSetsBehavior, NestedSetsQueryBehavior};
use yii2\extensions\nestedsets\tests\support\model\{
    ConcurrencyTree,
    ExtendableMultipleTree,
    JoinedTree,
    LockedTree,
    MultipleTree,
    MultipleTreeQuery,
    NonTransactionalTree,
    StorageValueTree,
    Tree,
    TreeQuery,
    TreeWithStrictValidation,
};

return [
    'phpstan' => ['application_type' => Application::class],
    'behaviors' => [
        ActiveRecord::class => [
            NestedSetsBehavior::class,
        ],
        ActiveQuery::class => [
            NestedSetsQueryBehavior::class,
        ],
        ConcurrencyTree::class => [
            NestedSetsBehavior::class,
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
        JoinedTree::class => [
            NestedSetsBehavior::class,
        ],
        LockedTree::class => [
            NestedSetsBehavior::class,
        ],
        StorageValueTree::class => [
            NestedSetsBehavior::class,
        ],
        NonTransactionalTree::class => [
            NestedSetsBehavior::class,
        ],
        TreeWithStrictValidation::class => [
            NestedSetsBehavior::class,
        ],
    ],
];
