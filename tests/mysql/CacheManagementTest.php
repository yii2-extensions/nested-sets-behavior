<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractCacheManagement;

#[Group('mysql')]
final class CacheManagementTest extends AbstractCacheManagement
{
    protected string|null $dsn = 'sqlite::memory:';
}
