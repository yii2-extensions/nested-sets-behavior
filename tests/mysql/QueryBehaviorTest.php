<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractQueryBehavior;

#[Group('mysql')]
final class QueryBehaviorTest extends AbstractQueryBehavior
{
    protected string|null $dsn = 'mysql:host=127.0.0.1;dbname=yiitest;charset=utf8mb4';

    protected string $password = 'root';
    protected string $username = 'root';
}
