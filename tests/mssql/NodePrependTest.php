<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodePrepend;
use yii2\extensions\nestedsets\tests\support\MSSQLConnection;

/**
 * Test suite for node prepend operations in nested sets tree behaviors using SQL Server.
 *
 * Provides comprehensive unit and integration tests for prepending nodes in nested sets tree structures on SQL Server,
 * ensuring correct tree structure, attribute updates, and validation logic for both single-tree and multi-tree models.
 *
 * Inherits tests from {@see AbstractNodePrepend} to validate node prepend operations, strict validation scenarios, and
 * XML dataset matching after structural changes, covering edge cases such as validation bypass, attribute refresh
 * requirements, and cross-tree operations.
 *
 * Key features.
 * - Covers both {@see Tree} and {@see MultipleTree} model scenarios.
 * - Ensures correct left, right, depth, and tree attribute updates after prepend operations for SQL Server.
 * - Tests for prepending new and existing nodes, including cross-tree operations.
 * - Validation of strict and non-strict prepend operations.
 * - XML dataset matching after structural changes.
 *
 * @see AbstractNodePrepend for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class NodePrependTest extends AbstractNodePrepend
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
