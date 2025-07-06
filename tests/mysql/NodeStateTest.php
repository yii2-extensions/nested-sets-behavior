<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeState;

#[Group('mysql')]
final class NodeStateTest extends AbstractNodeState
{
    protected string|null $dsn = 'sqlite::memory:';
}
