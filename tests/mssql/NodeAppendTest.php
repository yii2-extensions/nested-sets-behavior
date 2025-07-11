<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeAppend;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for node append and root promotion in nested sets tree behaviors using SQL Server.
 *
 * Provides comprehensive unit and integration tests for appending nodes and promoting nodes to root in nested sets tree
 * structures on SQL Server, ensuring correct tree structure, attribute updates, and validation logic for both
 * single-tree and multi-tree models.
 *
 * Inherits tests from {@see AbstractNodeAppend} to validate node append operations, strict validation scenarios, root
 * promotion, and XML dataset matching after structural changes, covering edge cases such as validation bypass,
 * attribute refresh requirements, and cross-tree operations.
 *
 * Key features.
 * - Covers both {@see Tree} and {@see MultipleTree} model scenarios.
 * - Cross-tree append operations for multi-tree models.
 * - Ensures correct left, right, depth, and tree attribute updates for SQL Server.
 * - Root promotion and attribute refresh verification.
 * - SQL Server-specific configuration for database connection and credentials.
 * - Validation of strict and non-strict append operations.
 * - XML dataset matching after structural changes.
 *
 * @see AbstractNodeAppend for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class NodeAppendTest extends AbstractNodeAppend
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();

        parent::setUp();
    }
}
