<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii\db\Exception;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;
use yii2\extensions\nestedsets\tests\support\model\MultipleTree;
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Test suite for mutation operations in nested sets tree behaviors using MySQL.
 *
 * Verifies correct handling of leaf node ordering and left attribute consistency in multiple tree models on MySQL.
 *
 * Ensures that the `leaves()` method returns nodes in the expected order after direct manipulation of left and right
 * attributes, maintaining data integrity and predictable query results.
 *
 * Key features.
 * - Ensures consistent results from the `leaves()` method.
 * - MySQL-specific configuration for database connection and credentials.
 * - Uses the multiple tree model for mutation scenarios.
 * - Validates leaf node detection and ordering after manual updates.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('mutation')]
final class MutationTest extends TestCase
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::MYSQL->connection();

        parent::setUp();
    }

    /**
     * @throws Exception if an unexpected error occurs during execution.
     */
    public function testLeavesMethodRequiresLeftAttributeOrderingForConsistentResults(): void
    {
        $this->createDatabase();

        $root = new MultipleTree(['name' => 'Root']);

        $root->makeRoot();

        $leaf1 = new MultipleTree(['name' => 'Leaf A']);

        $leaf1->appendTo($root);

        $leaf2 = new MultipleTree(['name' => 'Leaf B']);

        $leaf2->appendTo($root);

        $initialLeaves = MultipleTree::find()->leaves()->all();

        self::assertCount(
            2,
            $initialLeaves,
            "Should have exactly '2' initial leaf nodes.",
        );

        $command = $this->getDb()->createCommand();

        $command->update('multiple_tree', ['lft' => 3, 'rgt' => 4], ['name' => 'Leaf B'])->execute();
        $command->update('multiple_tree', ['lft' => 5, 'rgt' => 6], ['name' => 'Leaf A'])->execute();
        $command->update('multiple_tree', ['lft' => 1, 'rgt' => 7], ['name' => 'Root'])->execute();

        $leaves = MultipleTree::find()->leaves()->all();

        /** @phpstan-var array<array{name: string, lft: int}> $expectedLeaves */
        $expectedLeaves = [
            ['name' => 'Leaf B', 'lft' => 3],
            ['name' => 'Leaf A', 'lft' => 5],
        ];

        self::assertCount(
            2,
            $leaves,
            "Should return exactly '2' leaf nodes.",
        );

        foreach ($leaves as $index => $leaf) {
            self::assertInstanceOf(
                MultipleTree::class,
                $leaf,
                "Leaf at index {$index} should be an instance of 'MultipleTree'.",
            );

            if (isset($expectedLeaves[$index])) {
                self::assertEquals(
                    $expectedLeaves[$index]['name'],
                    $leaf->getAttribute('name'),
                    "Leaf at index {$index} should be {$expectedLeaves[$index]['name']} in correct order.",
                );
                self::assertEquals(
                    $expectedLeaves[$index]['lft'],
                    $leaf->getAttribute('lft'),
                    "Leaf at index {$index} should have left value {$expectedLeaves[$index]['lft']}.",
                );
            }
        }
    }
}
