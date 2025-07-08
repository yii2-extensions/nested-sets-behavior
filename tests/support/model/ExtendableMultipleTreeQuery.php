<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveQuery;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;

/**
 * Active Query class for {@see ExtendableMultipleTree} with nested sets query behavior support.
 *
 * Provides an Active Query implementation tailored for the {@see ExtendableMultipleTree} model, enabling integration
 * with the {@see NestedSetsQueryBehavior} for hierarchical data operations in multiple tree scenarios.
 *
 * This class attaches the nested sets query behavior to facilitate tree traversal and structure queries on models
 * representing multiple tree hierarchies.
 *
 * Key features:
 * - Attaches {@see NestedSetsQueryBehavior} for nested sets operations.
 * - Designed for use with {@see ExtendableMultipleTree} in test environments.
 * - Supports hierarchical queries for models with multiple tree columns.
 *
 * @template T of ExtendableMultipleTree
 *
 * @extends ActiveQuery<T>
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class ExtendableMultipleTreeQuery extends ActiveQuery
{
    public function behaviors(): array
    {
        return [
            'nestedSetsQueryBehavior' => NestedSetsQueryBehavior::class,
        ];
    }
}
