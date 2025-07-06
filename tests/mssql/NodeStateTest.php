<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeState;

#[Group('mssql')]
final class NodeStateTest extends AbstractNodeState
{
    protected string $driverName = 'sqlsrv';
    protected string|null $dsn = 'sqlsrv:Server=127.0.0.1,1433;Database=yiitest;Encrypt=no';
    protected string $password = 'SA';
    protected string $username = 'YourStrong!Passw0rd';
}
