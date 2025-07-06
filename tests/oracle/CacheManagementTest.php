<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\oracle;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractCacheManagement;

#[Group('oci')]
final class CacheManagementTest extends AbstractCacheManagement
{
    protected string $driverName = 'oci';
    protected string|null $dsn = 'oci:dbname=localhost/XE;charset=AL32UTF8;';
    protected string $password = 'root';
    protected string $username = 'system';
}
