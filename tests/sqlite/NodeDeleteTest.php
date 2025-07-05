<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeDelete;

#[Group('sqlite')]
final class NodeDeleteTest extends AbstractNodeDelete
{
    protected string|null $dsn = 'sqlite::memory:';
}
