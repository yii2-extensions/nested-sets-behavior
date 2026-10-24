<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

/**
 * Active Record model without transactional operations used by the transaction enforcement tests.
 */
final class NonTransactionalTree extends Tree
{
    public function transactions(): array
    {
        return [];
    }
}
