<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExceptionHandling;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for exception handling in nested sets tree behaviors using SQL Server.
 *
 * Verifies correct exception throwing and error messages for invalid node operations and edge cases in nested sets tree
 * structures on SQL Server, covering both single and multiple tree models.
 *
 * Inherits unit tests from {@see AbstractExceptionHandling} to ensure robustness of the nested sets behavior by
 * simulating invalid operations such as appending, inserting, deleting, and making root nodes under unsupported
 * conditions.
 *
 * Key features.
 * - Ensures error handling consistency for unsupported operations on SQL Server.
 * - Full coverage for invalid append, insert, delete, and makeRoot operations.
 * - SQL Server-specific configuration for database connection and credentials.
 * - Support for both single-tree and multi-tree models.
 * - Tests for exception messages and types in various edge cases.
 *
 * @see AbstractExceptionHandling for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class ExceptionHandlingTest extends AbstractExceptionHandling
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();

        parent::setUp();
    }
}
