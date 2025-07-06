<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExtensibility;

#[Group('sqlite')]
final class ExtensibilityTest extends AbstractExtensibility
{
    protected string|null $dsn = 'sqlite::memory:';
}
