<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExceptionHandling;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Runs the {@see AbstractExceptionHandling} scenarios against SQLite.
 */
#[Group('sqlite')]
final class ExceptionHandlingTest extends AbstractExceptionHandling
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::SQLITE->connection();

        parent::setUp();
    }
}
