<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use RuntimeException;
use SimpleXMLElement;
use Yii;
use yii\base\InvalidArgumentException;
use yii\console\Application;
use yii\db\{ActiveQuery, ActiveRecord, Connection, SchemaBuilderTrait};
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};

use function array_merge;
use function array_values;
use function dom_import_simplexml;
use function file_get_contents;
use function preg_replace;
use function simplexml_load_string;
use function str_replace;

/**
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
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    use SchemaBuilderTrait;
    protected string $driverName = 'sqlite';

    protected string|null $dsn = null;
    protected string $fixtureDirectory = __DIR__ . '/support/data/';
    protected string $password = '';
    protected string $username = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConsoleApplication();
    }

    public function getDb(): Connection
    {
        return Yii::$app->getDb();
    }

    /**
     * Asserts that a list of tree nodes matches the expected order.
     *
     * @param array $nodesList List of tree nodes to validate
     * @param array $expectedOrder Expected order of node names
     * @param string $nodeType Type of nodes being tested (for error messages)
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
     * @param ActiveQuery $query The query to check
     * @param string $methodName Name of the method being tested
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
     * @phpstan-import-type DataSetType from TestCase
     *
     * @phpstan-param DataSetType $dataSet
     */
    protected function buildFlatXMLDataSet(array $dataSet): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><dataset></dataset>');

        foreach ($dataSet as $item) {
            $treeElement = $xml->addChild($item['type']);
            $treeElement?->addAttribute('id', $this->convertToString($item['id']));

            if ($item['type'] === 'multiple_tree') {
                $treeElement?->addAttribute('tree', $this->convertToString($item['tree']));
            }

            $treeElement?->addAttribute('lft', $this->convertToString($item['lft']));
            $treeElement?->addAttribute('rgt', $this->convertToString($item['rgt']));
            $treeElement?->addAttribute('depth', $this->convertToString($item['depth']));
            $treeElement?->addAttribute('name', $this->convertToString($item['name']));
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

        // Replace the tags with 4 spaces
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
     * Converts a value to string, handling Oracle resource types correctly.
     *
     * Oracle database may return numeric values as resource types when using `asArray()` with {@see ActiveRecord}.
     *
     * This method properly converts those resources to strings for use with {@see SimpleXMLElement::addAttribute()}.
     *
     * @param int|string|resource|null $value The value to convert to string
     *
     * @return string The converted string value
     */
    protected function convertToString($value): string
    {
        if (is_resource($value)) {
            $content = stream_get_contents($value);

            if (is_string($content)) {
                return trim($content);
            }

            return '';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    protected function createDatabase(): void
    {
        $command = $this->getDb()->createCommand();

        if ($this->getDb()->getTableSchema('tree', true) !== null) {
            $command->dropTable('tree')->execute();
        }

        if ($this->getDb()->getTableSchema('multiple_tree', true) !== null) {
            $command->dropTable('multiple_tree')->execute();
        }

        $primaryKey = $this->driverName === 'oci'
            ? 'NUMBER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY'
            : $this->primaryKey()->notNull();

        $command->createTable(
            'tree',
            [
                'id' => $primaryKey,
                'name' => $this->text()->notNull(),
                'lft' => $this->integer()->notNull(),
                'rgt' => $this->integer()->notNull(),
                'depth' => $this->integer()->notNull(),
            ],
        )->execute();

        $command->createTable(
            'multiple_tree',
            [
                'id' => $primaryKey,
                'tree' => $this->integer(),
                'name' => $this->text()->notNull(),
                'lft' => $this->integer()->notNull(),
                'rgt' => $this->integer()->notNull(),
                'depth' => $this->integer()->notNull(),
            ],
        )->execute();
    }

    /**
     * Creates a tree structure based on a hierarchical definition.
     *
     * @param array $structure Hierarchical tree structure definition
     * @param array $updates Database updates to apply after creation
     * @param string $modelClass The model class to use (Tree::class or MultipleTree::class)
     *
     * @throws InvalidArgumentException if the structure array is empty.
     *
     * @return MultipleTree|Tree The root node
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

    protected function generateFixtureTree(): void
    {
        $this->createDatabase();

        $command = $this->getDb()->createCommand();

        // Load XML fixture data into database tables
        $xml = new SimpleXMLElement("{$this->fixtureDirectory}/test.xml", 0, true);

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

    protected function mockConsoleApplication(): void
    {
        new Application(
            [
                'id' => 'testapp',
                'basePath' => dirname(__DIR__),
                'components' => [
                    'db' => [
                        'class' => Connection::class,
                        'dsn' => $this->dsn !== null ? $this->dsn : 'sqlite::memory:',
                        'password' => $this->password,
                        'username' => $this->username,
                    ],
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
        return match ($this->driverName) {
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
     * Applies database updates to tree nodes.
     *
     * @param array $updates Array of updates to apply.
     * @param string $tableName Name of the table to apply updates to.
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
     * @param MultipleTree|Tree $parent The parent node
     * @param array $nodes Children definition (can be strings or arrays)
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
