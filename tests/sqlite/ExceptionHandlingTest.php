<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExceptionHandling;

#[Group('sqlite')]
final class ExceptionHandlingTest extends AbstractExceptionHandling
{
    protected string|null $dsn = 'sqlite::memory:';
}
