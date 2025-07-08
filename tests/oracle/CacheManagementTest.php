<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\oracle;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractCacheManagement;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for cache invalidation in nested sets tree behaviors using Oracle.
 *
 * Verifies correct cache management, invalidation, and memoization for nested sets tree structures in Oracle
 * environments, covering node insertions, updates, deletions, and structural changes for both single and multiple tree
 * models.
 *
 * Inherits integration and unit tests from {@see AbstractCacheManagement} to ensure cache lifecycle correctness,
 * including depth, left, and right attribute handling, and supports both manual and automatic cache invalidation
 * scenarios.
 *
 * Key features.
 * - Ensures compatibility and correctness of cache logic on the Oracle platform.
 * - Full coverage of cache population, invalidation, and memoization for nested sets behaviors.
 * - Oracle-specific configuration for database connection and credentials.
 *
 * @see AbstractCacheManagement for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('oci')]
final class CacheManagementTest extends AbstractCacheManagement
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::ORACLE->connection();

        parent::setUp();
    }
}
