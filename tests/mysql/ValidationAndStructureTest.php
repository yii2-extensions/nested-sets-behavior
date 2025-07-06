<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractValidationAndStructure;

#[Group('mysql')]
final class ValidationAndStructureTest extends AbstractValidationAndStructure
{
    protected string|null $dsn = 'sqlite::memory:';
}
