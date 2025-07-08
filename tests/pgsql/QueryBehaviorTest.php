<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\pgsql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractQueryBehavior;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for query behavior in nested sets tree behaviors using PostgreSQL.
 *
 * Provides comprehensive unit tests for query methods related to leaf and root node retrieval, ordering, and behavior
 * attachment in both single-tree and multi-tree nested sets models on PostgreSQL, ensuring correctness of query methods
 * such as `leaves()` and `roots()`, including ordering guarantees, SQL generation, and error handling when the behavior
 * is detached or not attached to the owner.
 *
 * Inherits tests from {@see AbstractQueryBehavior} to validate deterministic ordering, correct node retrieval, SQL
 * structure, and exception handling for query behaviors.
 *
 * Key features.
 * - Ensures deterministic ordering of results by left and tree attributes.
 * - PostgreSQL-specific configuration for database connection and credentials.
 * - Tests for correct leaf and root node retrieval in {@see Tree} and {@see MultipleTree} models.
 * - Validates SQL query structure for ordering requirements.
 * - Verifies exception handling when the behavior is detached or not attached to the query owner.
 *
 * @see AbstractQueryBehavior for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('pgsql')]
final class QueryBehaviorTest extends AbstractQueryBehavior
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::PGSQL->connection();

        parent::setUp();
    }
}
