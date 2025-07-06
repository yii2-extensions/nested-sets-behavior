<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeAppend;

#[Group('mysql')]
final class NodeAppendTest extends AbstractNodeAppend
{
    protected string|null $dsn = 'mysql:host=127.0.0.1;dbname=yiitest;charset=utf8mb4';
    protected string $user = 'root';
    protected string $password = 'root';
}
