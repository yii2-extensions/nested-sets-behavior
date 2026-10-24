<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

/**
 * Active Record model with a `revision` optimistic lock used by the structural operation tests.
 *
 * @property int $revision
 */
final class LockedTree extends Tree
{
    public function optimisticLock(): string
    {
        return 'revision';
    }
}
