<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractNodeDelete;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for node deletion in nested sets tree behaviors using SQLite.
 *
 * Provides comprehensive unit tests for node and subtree deletion operations in nested sets tree structures on SQLite,
 * ensuring correct state transitions, affected row counts, and data integrity after deletions for both single-tree and
 * multi-tree models.
 *
 * Inherits tests from {@see AbstractNodeDelete} to validate node deletion, subtree removals, abort scenarios,
 * transactional behavior, and update operations on node attributes, covering edge cases and XML dataset consistency
 * after deletions.
 *
 * Key features.
 * - Covers update operations and affected row count for node attribute changes.
 * - Ensures correct affected row counts for node and subtree deletions in both {@see Tree} and {@see MultipleTree}
 *   models.
 * - SQLite-specific configuration for database connection and credentials.
 * - Tests aborting deletions via `beforeDelete()` and transactional behavior.
 * - Validates XML dataset consistency after deletions.
 * - Verifies node state transitions after `deleteWithChildren()` (new record status, old attributes).
 *
 * @see AbstractNodeDelete for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('sqlite')]
final class NodeDeleteTest extends AbstractNodeDelete
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::SQLITE->connection();

        parent::setUp();
    }
}
