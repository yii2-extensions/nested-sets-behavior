<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\pgsql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeState;

#[Group('pgsql')]
final class NodeStateTest extends AbstractNodeState
{
    protected string|null $dsn = 'pgsql:host=localhost;dbname=yiitest;port=5432;';
    protected string $password = 'root';
    protected string $username = 'root';
}
