<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use RuntimeException;
use SimpleXMLElement;
use Yii;
use yii\base\{InvalidArgumentException, InvalidConfigException};
use yii\console\Application;
use yii\db\{ActiveQuery, ActiveRecord, Connection, Exception, SchemaBuilderTrait};
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};
use yii2\extensions\nestedsets\tests\support\stub\EchoMigrateController;

use function array_merge;
use function array_values;
use function dirname;
use function dom_import_simplexml;
use function file_get_contents;
use function is_int;
use function is_string;
use function ob_get_clean;
use function ob_implicit_flush;
use function ob_start;
use function preg_replace;
use function simplexml_load_string;
use function str_replace;

/**
 * Base test case for nested sets behavior test suites.
 *
 * Provides common setup, database management, fixture loading, and assertion utilities for all nested sets tests.
 *
 * This class centralizes logic for initializing the test environment, managing database state, and verifying tree
 * structures, ensuring consistency and reducing duplication across test cases for different database drivers and
 * scenarios.
 *
 * Key features.
 * - Assertion helpers for validating node order and query structure.
 * - Integration with custom migration and schema management.
 * - Shared database connection and fixture directory configuration.
 * - Support for both single-tree and multi-tree models.
 * - Utilities for creating, resetting, and populating test databases.
 * - XML fixture generation and loading for reproducible test data.
 *
 * @see EchoMigrateController for migration handling.
 * @see MultipleTree for multi-tree model.
 * @see Tree for single-tree model.
 *
 * @phpstan-type DataSetType = list<
 *   array{
 *     id: int,
 *     name: string,
 *     tree: int,
 *     type: 'multiple_tree'|'tree',
 *     lft: int,
 *     rgt: int,
 *     depth: int,
 *   }
 * >
 * @phpstan-type NodeChildren array<string|array{name: string, children?: array<mixed>}>
 * @phpstan-type TreeStructure array<array<mixed>>
 * @phpstan-type UpdateData array<array{name: string, lft?: int, rgt?: int, depth?: int}>
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use SchemaBuilderTrait;

    /**
     * Database connection configuration.
     *
     * @phpstan-var string[]
     */
    protected array $connection = [];

    /**
     * Directory where fixture XML files are stored.
     */
    protected string $fixtureDirectory = __DIR__ . '/support/data/';

    /**
     * @throws InvalidConfigException if the configuration is invalid or incomplete.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConsoleApplication();
    }

    /**
     * Asserts that a list of tree nodes matches the expected order.
     *
     * @param array $nodesList List of tree nodes to validate.
     * @param array $expectedOrder Expected order of node names.
     * @param string $nodeType Type of nodes being tested (for error messages).
     *
     * @phpstan-param array<ActiveRecord> $nodesList
     * @phpstan-param array<string> $expectedOrder
     */
    protected function assertNodesInCorrectOrder(array $nodesList, array $expectedOrder, string $nodeType): void
    {
        self::assertCount(
            count($expectedOrder),
            $nodesList,
            "{$nodeType} list should contain exactly '" . count($expectedOrder) . "' elements.",
        );

        foreach ($nodesList as $index => $node) {
            self::assertInstanceOf(
                Tree::class,
                $node,
                "{$nodeType} at index {$index} should be an instance of 'Tree'.",
            );

            if (isset($expectedOrder[$index])) {
                self::assertEquals(
                    $expectedOrder[$index],
                    $node->getAttribute('name'),
                    "{$nodeType} at index {$index} should be {$expectedOrder[$index]} in correct 'lft' order.",
                );
            }
        }
    }

    /**
     * Asserts that a query contains ORDER BY clause with 'lft' column.
     *
     * @param ActiveQuery $query Query to check.
     * @param string $methodName Name of the method being tested.
     *
     * @phpstan-param ActiveQuery<ActiveRecord> $query
     */
    protected function assertQueryHasOrderBy(ActiveQuery $query, string $methodName): void
    {
        $sql = $query->createCommand()->getRawSql();

        self::assertStringContainsString(
            'ORDER BY',
            $this->replaceQuotes($sql),
            "'{$methodName}' query should include 'ORDER BY' clause for deterministic results.",
        );

        self::assertStringContainsString(
            $this->replaceQuotes('[[lft]]'),
            $sql,
            "'{$methodName}' query should order by 'left' attribute for consistent ordering.",
        );
    }

    /**
     * Builds a flat XML dataset from a given data set array.
     *
     * @return string Formatted XML string.
     *
     * @phpstan-import-type DataSetType from TestCase
     *
     * @phpstan-param DataSetType $dataSet
     */
    protected function buildFlatXMLDataSet(array $dataSet): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><dataset></dataset>');

        foreach ($dataSet as $item) {
            $treeElement = $xml->addChild($item['type']);
            $treeElement?->addAttribute('id', (string) $item['id']);

            if ($item['type'] === 'multiple_tree') {
                $treeElement?->addAttribute('tree', (string) $item['tree']);
            }

            $treeElement?->addAttribute('lft', (string) $item['lft']);
            $treeElement?->addAttribute('rgt', (string) $item['rgt']);
            $treeElement?->addAttribute('depth', (string) $item['depth']);
            $treeElement?->addAttribute('name', $item['name']);
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;

        if ($dom === null) {
            throw new RuntimeException('Failed to create DOM from SimpleXMLElement.');
        }

        $dom->formatOutput = true;
        $formattedXml = $dom->saveXML();

        if ($formattedXml === false) {
            throw new RuntimeException('Failed to save XML from DOM.');
        }

        // Manually indent child elements with 4 spaces for consistent fixture formatting.
        // DOM's formatOutput doesn't provide control over indentation depth.
        return str_replace(
            [
                '<tree', '<multiple_tree'],
            [
                '  <tree',
                '  <multiple_tree',
            ],
            $formattedXml,
        );
    }

    /**
     * Creates the database schema and resets the tables for testing.
     *
     * This method drops existing tables and runs migrations to ensure a clean state.
     *
     * @throws Exception if an unexpected error occurs during execution.
     * @throws RuntimeException if a runtime error prevents the operation from completing successfully.
     */
    protected function createDatabase(): void
    {
        $command = $this->getDb()->createCommand();
        $dropTables = [
            'migration',
            'multiple_tree',
            'tree',
        ];

        try {
            $this->runMigrate('down', ['all']);
        } catch (RuntimeException) {
            // Ignore errors when rolling back migrations on a potentially fresh database
        }

        foreach ($dropTables as $table) {
            if ($this->getDb()->getTableSchema($table, true) !== null) {
                $command->dropTable($table)->execute();
            }
        }

        $this->runMigrate('up');
    }

    /**
     * Creates a tree structure based on a hierarchical definition.
     *
     * @param array $structure Hierarchical tree structure definition.
     * @param array $updates Database updates to apply after creation.
     * @param string $modelClass Model class to use ({@see Tree::class} or {@see MultipleTree::class}.
     *
     * @throws Exception if an unexpected error occurs during execution.
     * @throws InvalidArgumentException if one or more arguments are invalid, of incorrect type or format.
     *
     * @return MultipleTree|Tree Root node.
     *
     * @phpstan-param TreeStructure $structure
     * @phpstan-param UpdateData $updates
     * @phpstan-param class-string<Tree|MultipleTree> $modelClass
     */
    protected function createTreeStructure(
        array $structure,
        array $updates = [],
        string $modelClass = Tree::class,
    ): Tree|MultipleTree {
        if ($structure === []) {
            throw new InvalidArgumentException('Tree structure cannot be empty.');
        }

        $this->createDatabase();

        $rootNode = null;

        foreach ($structure as $rootDefinition) {
            $root = new $modelClass(['name' => $rootDefinition['name'] ?? 'Root']);
            $root->makeRoot();

            if ($rootNode === null) {
                $rootNode = $root;
            }

            if (isset($rootDefinition['children'])) {
                /** @phpstan-var NodeChildren $children */
                $children = $rootDefinition['children'];
                $this->createChildrenRecursively($root, $children);
            }
        }

        $this->applyUpdates($updates, $modelClass === MultipleTree::class ? 'multiple_tree' : 'tree');

        $rootNode->refresh();

        return $rootNode;
    }

    /**
     * Generates fixture data for testing tree structures.
     *
     * This method creates a database schema and populates it with predefined XML fixture data.
     *
     * It is used to set up the initial state of the database for tests that require specific tree structures.
     *
     * @throws Exception if an unexpected error occurs during execution.
     * @throws RuntimeException if a runtime error prevents the operation from completing successfully.
     */
    protected function generateFixtureTree(): void
    {
        $this->createDatabase();

        $command = $this->getDb()->createCommand();

        // Load XML fixture data into database tables
        $xml = $this->loadFixtureXML('test.xml');

        $children = $xml->children() ?? [];

        foreach ($children as $element => $treeElement) {
            match ($element === 'tree') {
                true => $command->insert(
                    'tree',
                    [
                        'name' => $treeElement['name'],
                        'lft' => $treeElement['lft'],
                        'rgt' => $treeElement['rgt'],
                        'depth' => $treeElement['depth'],
                    ],
                )->execute(),
                default => $command->insert(
                    'multiple_tree',
                    [
                        'tree' => $treeElement['tree'],
                        'name' => $treeElement['name'],
                        'lft' => $treeElement['lft'],
                        'rgt' => $treeElement['rgt'],
                        'depth' => $treeElement['depth'],
                    ],
                )->execute(),
            };
        }
    }

    /**
     * Returns a dataset containing all tree nodes from both {@see Tree} and {@see MultipleTree} models.
     *
     * @return array Dataset containing all tree nodes.
     *
     * @phpstan-import-type DataSetType from TestCase
     *
     * @phpstan-return DataSetType
     */
    protected function getDataSet(): array
    {
        $dataSetTree = Tree::find()->orderBy(['id' => SORT_ASC])->asArray()->all();

        foreach ($dataSetTree as $key => $value) {
            $dataSetTree[$key]['type'] = 'tree';
            $dataSetTree[$key]['tree'] = 0;
        }

        $dataSetMultipleTree = MultipleTree::find()->orderBy(['id' => SORT_ASC])->asArray()->all();

        foreach ($dataSetMultipleTree as $key => $value) {
            $dataSetMultipleTree[$key]['type'] = 'multiple_tree';
        }

        return array_merge($dataSetTree, $dataSetMultipleTree);
    }

    /**
     * Returns a dataset containing only {@see MultipleTree} nodes.
     *
     * @return array Dataset containing only {@see MultipleTree} nodes.
     *
     * @phpstan-import-type DataSetType from TestCase
     *
     * @phpstan-return DataSetType
     */
    protected function getDataSetMultipleTree(): array
    {
        $dataSetMultipleTree = MultipleTree::find()->orderBy(['id' => SORT_ASC])->asArray()->all();

        foreach ($dataSetMultipleTree as $key => $value) {
            $dataSetMultipleTree[$key]['type'] = 'multiple_tree';
        }

        return array_values($dataSetMultipleTree);
    }

    /**
     * Returns the database connection used for tests.
     */
    protected function getDb(): Connection
    {
        return Yii::$app->getDb();
    }

    /**
     * Returns a dataset containing only {@see Tree} nodes.
     *
     * @throws RuntimeException if a runtime error prevents the operation from completing successfully.
     *
     * @return SimpleXMLElement Dataset containing only {@see Tree} nodes.
     */
    protected function loadFixtureXML(string $fileName): SimpleXMLElement
    {
        $filePath = "{$this->fixtureDirectory}/{$fileName}";

        $file = file_get_contents($filePath);

        if ($file === false) {
            throw new RuntimeException("Failed to load fixture file: {$filePath}");
        }

        $simpleXML = simplexml_load_string($file);

        if ($simpleXML === false) {
            throw new RuntimeException("Failed to parse XML from fixture file: {$filePath}");
        }

        return $simpleXML;
    }

    /**
     * Mocks the console application for testing purposes.
     *
     * This method initializes a new console application instance with a database connection.
     *
     * It is used to set up the environment for running console commands in tests.
     *
     * @throws InvalidConfigException if the configuration is invalid or incomplete.
     */
    protected function mockConsoleApplication(): void
    {
        new Application(
            [
                'id' => 'testapp',
                'basePath' => dirname(__DIR__),
                'components' => [
                    'db' => $this->connection,
                ],
            ],
        );
    }

    /**
     * Adjust dbms specific escaping.
     *
     * @param string $sql SQL to adjust.
     *
     * @return string Adjusted SQL.
     */
    protected function replaceQuotes(string $sql): string
    {
        return match ($this->getDb()->driverName) {
            'mysql', 'sqlite' => str_replace(
                ['[[', ']]'],
                '`',
                $sql,
            ),
            'oci' => str_replace(
                ['[[', ']]'],
                '"',
                $sql,
            ),
            'pgsql' => str_replace(
                ['\\[', '\\]'],
                ['[', ']'],
                preg_replace('/(\[\[)|((?<!(\[))\]\])/', '"', $sql) ?? $sql,
            ),
            'sqlsrv' => str_replace(
                ['[[', ']]'],
                ['[', ']'],
                $sql,
            ),
            default => $sql,
        };
    }

    /**
     * Runs a migration action with the specified parameters.
     *
     * @return mixed Result of the migration action.
     *
     * @phpstan-param array<array-key, mixed> $params
     */
    protected function runMigrate(string $action, array $params = []): mixed
    {
        $migrate = new EchoMigrateController(
            'migrate',
            Yii::$app,
            [
                'migrationPath' => dirname(__DIR__) . '/migrations',
                'interactive' => false,
            ],
        );

        ob_start();
        ob_implicit_flush(false);

        $result = $migrate->run($action, $params);

        $capture = ob_get_clean();

        if (is_int($result) && $result !== 0) {
            throw new RuntimeException("Migration '{$action}' failed with code {$result}.\nOutput: {$capture}");
        }

        return $result;
    }

    /**
     * Applies database updates to tree nodes.
     *
     * @param array $updates Array of updates to apply.
     * @param string $tableName Name of the table to apply updates to.
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * @phpstan-param UpdateData $updates
     */
    private function applyUpdates(array $updates, string $tableName): void
    {
        if ($updates === []) {
            return;
        }

        $command = $this->getDb()->createCommand();

        foreach ($updates as $update) {
            $name = $update['name'];

            unset($update['name']);

            $command->update($tableName, $update, ['name' => $name])->execute();
        }
    }

    /**
     * Recursively creates children for a given parent node.
     *
     * @param MultipleTree|Tree $parent Parent node.
     * @param array $nodes Children definition (can be strings or arrays).
     *
     * @phpstan-param NodeChildren $nodes
     */
    private function createChildrenRecursively(Tree|MultipleTree $parent, array $nodes): void
    {
        foreach ($nodes as $nodeDefinition) {
            if (is_string($nodeDefinition)) {
                $node = new ($parent::class)(['name' => $nodeDefinition]);
                $node->appendTo($parent);
            } else {
                $node = new ($parent::class)(['name' => $nodeDefinition['name']]);
                $node->appendTo($parent);

                if (isset($nodeDefinition['children'])) {
                    /** @phpstan-var NodeChildren $children */
                    $children = $nodeDefinition['children'];
                    $this->createChildrenRecursively($node, $children);
                }
            }
        }
    }
}
