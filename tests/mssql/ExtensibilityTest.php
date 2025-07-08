<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mssql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractExtensibility;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Test suite for extensibility in nested sets tree behaviors using SQL Server.
 *
 * Verifies that protected methods in the nested sets behavior remain accessible and customizable for subclassing
 * scenarios on SQL Server, ensuring extensibility for advanced use cases in both single-tree and multi-tree models.
 *
 * Inherits unit tests from {@see AbstractExtensibility} to validate the exposure and correct execution of key internal
 * methods, supporting framework extension and advanced customization in descendant classes.
 *
 * Key features.
 * - Ensures protected methods are accessible for subclass extension.
 * - Supports both single-tree and multi-tree model scenarios.
 * - Tests before-insert and move operations for extensibility.
 * - Validates extensibility for root and non-root node operations.
 * - Verifies correct attribute assignment by protected methods.
 *
 * @see AbstractExtensibility for test logic and scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mssql')]
final class ExtensibilityTest extends AbstractExtensibility
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MSSQL->connection();

        parent::setUp();
    }
}
