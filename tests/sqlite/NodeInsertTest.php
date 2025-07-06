<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeInsert;

#[Group('sqlite')]
final class NodeInsertTest extends AbstractNodeInsert
{
    protected string|null $dsn = 'sqlite::memory:';
}
