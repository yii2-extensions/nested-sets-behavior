<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractValidationAndStructure;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for validation and structural integrity in nested sets tree behaviors using SQL Server.
 *
 * Provides focused unit tests for validating node creation, root assignment, and structural attribute correctness in
 * nested sets tree models on SQL Server, including strict validation scenarios and direct manipulation of node
 * attributes during insertion.
 *
 * Inherits tests from {@see AbstractValidationAndStructure} to ensure correct node validation logic, left/right
 * attribute shifting, and depth assignment when creating root nodes, appending children, and invoking internal behavior
 * methods, covering both validation-enabled and validation-bypassed operations.
 *
 * Key features.
 * - Ensures correct attribute assignment when appending children to root nodes.
 * - SQL Server-specific configuration for database connection and credentials.
 * - Tests strict validation logic for root node creation with and without validation enforcement.
 * - Validates direct invocation of behavior hooks for node attribute initialization.
 * - Verifies left, right, and depth attribute values after root and child node operations.
 *
 * @see AbstractValidationAndStructure for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class ValidationAndStructureTest extends AbstractValidationAndStructure
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();

        parent::setUp();
    }
}
