<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeDelete;

/**
 * Test suite for node deletion in nested sets tree behaviors using SQL Server.
 *
 * Provides comprehensive unit tests for node and subtree deletion operations in nested sets tree structures on SQL
 * Server, ensuring correct state transitions, affected row counts, and data integrity after deletions for both
 * single-tree and multi-tree models.
 *
 * Inherits tests from {@see AbstractNodeDelete} to validate node deletion, subtree removals, abort scenarios,
 * transactional behavior, and update operations on node attributes, covering edge cases and XML dataset consistency
 * after deletions.
 *
 * Key features.
 * - Covers update operations and affected row count for node attribute changes.
 * - Ensures correct affected row counts for node and subtree deletions in both {@see Tree} and {@see MultipleTree}
 *   models.
 * - SQL Server-specific configuration for database connection and credentials.
 * - Tests aborting deletions via `beforeDelete()` and transactional behavior.
 * - Validates XML dataset consistency after deletions.
 * - Verifies node state transitions after `deleteWithChildren()` (new record status, old attributes).
 *
 * @see AbstractNodeDelete for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class NodeDeleteTest extends AbstractNodeDelete
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
