<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractTreeTraversal;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for tree traversal and relationship methods in nested sets tree behaviors using MySQL.
 *
 * Provides comprehensive unit tests for verifying traversal methods, node ordering, and parent/child/leaf relationships
 * in both single-tree and multi-tree nested sets models on MySQL.
 *
 * Inherits tests from {@see AbstractTreeTraversal} to ensure correctness and determinism of children, leaves, parents,
 * next, and previous node retrieval, including order-by requirements and depth constraints, by testing various tree
 * structures and update scenarios.
 *
 * Key features.
 * - Covers both {@see Tree} and {@see MultipleTree} model scenarios.
 * - Ensures correct node ordering and deterministic traversal for children, leaves, and parents.
 * - MySQL-specific configuration for database connection and credentials.
 * - Tests for order-by enforcement and depth constraints in traversal queries.
 * - Validation of structure updates and relationship methods on MySQL.
 *
 * @see AbstractTreeTraversal for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mysql')]
final class TreeTraversalTest extends AbstractTreeTraversal
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MYSQL->connection();

        parent::setUp();
    }
}
