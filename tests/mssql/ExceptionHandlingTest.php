<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExceptionHandling;

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
    /**
     * Database driver name for SQL Server.
     */
    protected string $driverName = 'sqlsrv';

    /**
     * Data Source Name (DSN) for SQL Server connection.
     */
    protected string|null $dsn = 'sqlsrv:Server=127.0.0.1,1433;Database=yiitest;Encrypt=no';

    /**
     * Password for SQL Server connection.
     */
    protected string $password = 'YourStrong!Passw0rd';

    /**
     * Username for SQL Server connection.
     */
    protected string $username = 'SA';
}
