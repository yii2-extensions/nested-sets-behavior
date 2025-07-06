<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\oracle;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExceptionHandling;

#[Group('oci')]
final class ExceptionHandlingTest extends AbstractExceptionHandling
{
    protected string $driverName = 'oci';
    protected string|null $dsn = 'oci:dbname=localhost/FREEPDB1;charset=AL32UTF8;';
    protected string $password = 'root';
    protected string $username = 'system';
}
