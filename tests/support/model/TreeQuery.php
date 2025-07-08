<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveQuery;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;

/**
 * Active Query class for {@see Tree} and {@see TreeWithStrictValidation} with nested sets query behavior support.
 *
 * Provides an Active Query implementation tailored for the {@see Tree} and {@see TreeWithStrictValidation} models,
 * enabling integration with the {@see NestedSetsQueryBehavior} for hierarchical data operations in single tree
 * scenarios.
 *
 * This class attaches the nested sets query behavior to facilitate tree traversal and structure queries on models
 * representing single tree hierarchies.
 *
 * Key features:
 * - Attaches {@see NestedSetsQueryBehavior} for nested sets operations.
 * - Designed for use with {@see Tree} and {@see TreeWithStrictValidation} in test environments.
 * - Supports hierarchical queries for models with single tree columns.
 *
 * @template T of Tree|TreeWithStrictValidation
 *
 * @extends ActiveQuery<T>
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class TreeQuery extends ActiveQuery
{
    /**
     * @phpstan-param class-string<T> $modelClass
     * @phpstan-param array<string, mixed> $config
     */
    public function __construct(string $modelClass, array $config = [])
    {
        parent::__construct($modelClass, $config);
    }

    public function behaviors(): array
    {
        return [
            'nestedSetsQueryBehavior' => NestedSetsQueryBehavior::class,
        ];
    }
}
