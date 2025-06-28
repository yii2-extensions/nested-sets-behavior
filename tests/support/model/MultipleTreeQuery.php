<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveQuery;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;

/**
 * @template T of MultipleTree
 *
 * @extends ActiveQuery<T>
 */
final class MultipleTreeQuery extends ActiveQuery
{
    public function behaviors(): array
    {
        return [
            'nestedSetsQueryBehavior' => NestedSetsQueryBehavior::class,
        ];
    }
}
