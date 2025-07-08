<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractQueryBehavior;
use yii2\extensions\nestedsets\tests\support\MSSQLConnection;

/**
 * Test suite for query behavior in nested sets tree behaviors using SQL Server.
 *
 * Provides comprehensive unit tests for query methods related to leaf and root node retrieval, ordering, and behavior
 * attachment in both single-tree and multi-tree nested sets models on SQL Server, ensuring correctness of query methods
 * such as `leaves()` and `roots()`, including ordering guarantees, SQL generation, and error handling when the behavior
 * is detached or not attached to the owner.
 *
 * Inherits tests from {@see AbstractQueryBehavior} to validate deterministic ordering, correct node retrieval, SQL
 * structure, and exception handling for query behaviors.
 *
 * Key features.
 * - Ensures deterministic ordering of results by left and tree attributes.
 * - SQL Server-specific configuration for database connection and credentials.
 * - Tests for correct leaf and root node retrieval in {@see Tree} and {@see MultipleTree} models.
 * - Validates SQL query structure for ordering requirements.
 * - Verifies exception handling when the behavior is detached or not attached to the query owner.
 *
 * @see AbstractQueryBehavior for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class QueryBehaviorTest extends AbstractQueryBehavior
{
    protected function setUp(): void
    {
        $this->driverName = MSSQLConnection::DRIVER_NAME->value;
        $this->dsn = MSSQLConnection::DSN->value;
        $this->password = MSSQLConnection::PASSWORD->value;
        $this->username = MSSQLConnection::USERNAME->value;

        parent::setUp();
    }
}
