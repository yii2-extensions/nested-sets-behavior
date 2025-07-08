<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\pgsql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Test suite for mutation operations in nested sets tree behaviors using PostgreSQL.
 *
 * Verifies correct node ordering and tree traversal logic for children queries in PostgreSQL-based nested sets trees.
 *
 * Ensures that the children retrieval method respects left/right boundaries and ordering requirements, validating that
 * tree traversal produces the expected node order after manual updates to the tree structure.
 *
 * Key features.
 * - Ensures correct handling of left/right attribute updates.
 * - PostgreSQL-specific configuration for database connection and credentials.
 * - Validates children node ordering for tree traversal.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mutation')]
final class MutationTest extends TestCase
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::PGSQL->connection();

        parent::setUp();
    }

    public function testChildrenMethodRequiresOrderByForCorrectTreeTraversal(): void
    {
        $expectedOrder = ['Child A', 'Child B', 'Child C'];

        $treeStructure = [
            ['name' => 'Root', 'children' => ['Child B', 'Child C', 'Child A']],
        ];

        $updates = [
            ['name' => 'Child B', 'lft' => 4, 'rgt' => 5],
            ['name' => 'Child C', 'lft' => 6, 'rgt' => 7],
            ['name' => 'Child A', 'lft' => 2, 'rgt' => 3],
            ['name' => 'Root', 'rgt' => 8],
        ];

        $tree = $this->createTreeStructure($treeStructure, $updates);
        $nodeList = $tree->children()->all();

        $this->assertNodesInCorrectOrder($nodeList, $expectedOrder, 'Child');
    }
}
