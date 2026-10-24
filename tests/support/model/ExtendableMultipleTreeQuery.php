<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveQuery;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;

/**
 * Active Query for {@see ExtendableMultipleTree} with the nested sets query behavior attached.
 *
 * @template T of ExtendableMultipleTree
 *
 * @extends ActiveQuery<T>
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
