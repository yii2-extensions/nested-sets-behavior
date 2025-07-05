<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodePrepend;

#[Group('sqlite')]
final class NodePrependTest extends AbstractNodePrepend
{
    protected string|null $dsn = 'sqlite::memory:';
}
