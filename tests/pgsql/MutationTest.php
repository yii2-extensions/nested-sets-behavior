<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\pgsql;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\TestCase;

#[Group('mutation')]
final class MutationTest extends TestCase
{
    protected string $driverName = 'pgsql';
    protected string|null $dsn = 'pgsql:host=localhost;dbname=yiitest;port=5432;';
    protected string $password = 'root';
    protected string $username = 'root';

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
