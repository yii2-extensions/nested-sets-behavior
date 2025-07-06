<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExceptionHandling;

#[Group('mssql')]
final class ExceptionHandlingTest extends AbstractExceptionHandling
{
    protected string $driverName = 'sqlsrv';
    protected string|null $dsn = 'sqlsrv:Server=127.0.0.1,1433;Database=yiitest;Encrypt=no';
    protected string $password = 'YourStrong!Passw0rd';
    protected string $username = 'SA';
}
