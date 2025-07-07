<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\mysql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\support\model\MultipleTree;
use yii2\extensions\nestedsets\tests\TestCase;

#[Group('mutation')]
final class MutationTest extends TestCase
{
    protected string $driverName = 'mysql';
    protected string|null $dsn = 'mysql:host=127.0.0.1;dbname=yiitest;charset=utf8mb4';
    protected string $password = 'root';
    protected string $username = 'root';

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

        /** @phpstan-var array<array{name: string, lft: int}> */
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
