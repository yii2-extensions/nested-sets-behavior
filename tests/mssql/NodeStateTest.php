<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeState;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for node state and relationship in nested sets tree behaviors using SQL Server.
 *
 * Provides comprehensive unit tests for verifying node state, parent-child relationships, and root/leaf detection in
 * both single-tree and multi-tree nested sets models on SQL Server.
 *
 * Inherits tests from {@see AbstractNodeState} to ensure correctness of methods that determine node ancestry, root
 * status, and leaf status by testing various edge cases and boundary conditions, such as equal left/right values and
 * ancestor chains.
 *
 * Key features.
 * - Coverage for both {@see Tree} and {@see MultipleTree} model implementations.
 * - Ensures correct behavior for left/right value manipulations and ancestor checks.
 * - SQL Server-specific configuration for database connection and credentials.
 * - Tests for `isChildOf()` under different ancestor and boundary scenarios.
 * - Validation of `isRoot()` and `isLeaf()` logic for root, leaf, and intermediate nodes.
 *
 * @see AbstractNodeState for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class NodeStateTest extends AbstractNodeState
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();

        parent::setUp();
    }
}
