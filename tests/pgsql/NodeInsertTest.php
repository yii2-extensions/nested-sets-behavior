<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\pgsql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeInsert;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for node insertion in nested sets tree behaviors using PostgreSQL.
 *
 * Provides comprehensive unit tests for node insertion operations in nested sets tree structures on PostgreSQL,
 * ensuring correct behavior for inserting nodes before and after targets, with and without validation, and across both
 * single-tree and multi-tree models.
 *
 * Inherits tests from {@see AbstractNodeInsert} to validate insertion logic, strict validation scenarios, cross-tree
 * insertions, and XML dataset matching after structural changes, covering edge cases such as validation bypass,
 * attribute refresh requirements, and multi-tree operations.
 *
 * Key features.
 * - Covers both {@see Tree} and {@see MultipleTree} model scenarios.
 * - Edge case handling for strict validation and cross-tree insertions.
 * - Ensures correct left, right, depth, and tree attribute updates for PostgreSQL.
 * - PostgreSQL-specific configuration for database connection and credentials.
 * - Validation of strict and non-strict insert operations.
 * - XML dataset matching after structural changes.
 *
 * @see AbstractNodeInsert for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('pgsql')]
final class NodeInsertTest extends AbstractNodeInsert
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::PGSQL->connection();

        parent::setUp();
    }
}
