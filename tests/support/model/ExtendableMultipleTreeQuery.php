<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveQuery;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;

/**
 * @template T of ExtendableMultipleTree
 *
 * @extends ActiveQuery<T>
 */
final class ExtendableMultipleTreeQuery extends ActiveQuery
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
