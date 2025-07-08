<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractCacheManagement;

/**
 * Test suite for cache invalidation in nested sets tree behaviors using SQL Server.
 *
 * Verifies correct cache management, invalidation, and memoization for nested sets tree structures in SQL Server
 * environments, covering node insertions, updates, deletions, and structural changes for both single and multiple tree
 * models.
 *
 * Inherits integration and unit tests from {@see AbstractCacheManagement} to ensure cache lifecycle correctness,
 * including depth, left, and right attribute handling, and supports both manual and automatic cache invalidation
 * scenarios.
 *
 * Key features.
 * - Ensures compatibility and correctness of cache logic on the SQL Server platform.
 * - Full coverage of cache population, invalidation, and memoization for nested sets behaviors.
 * - SQL Server-specific configuration for database connection and credentials.
 *
 * @see AbstractCacheManagement for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class CacheManagementTest extends AbstractCacheManagement
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
