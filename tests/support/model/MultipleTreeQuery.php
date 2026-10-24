<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveQuery;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;

/**
 * Active Query for {@see MultipleTree} with the nested sets query behavior attached.
 *
 * @template T of MultipleTree
 *
 * @extends ActiveQuery<T>
 */
final class MultipleTreeQuery extends ActiveQuery
{
    /**
     * @param class-string<T> $modelClass
     * @param array<string, mixed> $config
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
