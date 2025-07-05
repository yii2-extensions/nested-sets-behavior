<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractTreeTraversal;

#[Group('sqlite')]
final class TreeTraversalTest extends AbstractTreeTraversal
{
    protected string|null $dsn = 'sqlite::memory:';
}
