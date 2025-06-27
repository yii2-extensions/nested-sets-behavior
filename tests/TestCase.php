<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use SimpleXMLElement;
use Yii;
use yii\console\Application;
use yii\db\{Connection, SchemaBuilderTrait};
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree};

class TestCase extends \PHPUnit\Framework\TestCase
{
    use SchemaBuilderTrait;
    protected string $fixtureDirectory = __DIR__ . '/support/data/';
    public function getDb(): Connection
    {
        return Yii::$app->getDb();
    }

    /**
     * @phpstan-param array<
     *   array{id: int, name: string, tree: int, type: string, lft: int, rgt: int, depth: int, name: string}
     * > $dataSet
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

        assert($dom !== null, 'Failed to create DOM from SimpleXMLElement.');

        $dom->formatOutput = true;
        $formattedXml = $dom->saveXML();

        assert($formattedXml !== false, 'Failed to save XML from DOM.');

        // Replace the tags with 4 spaces
        return str_replace(['<tree', '<multiple_tree'], ['  <tree', '  <multiple_tree'], $formattedXml);
    }

    protected function createDatabase(): void
    {
        $command = $this->getDb()->createCommand();

        if ($this->getDb()->getTableSchema('tree', true) !== null) {
            $command->dropTable('tree');
        }

        if ($this->getDb()->getTableSchema('multiple_tree', true) !== null) {
            $command->dropTable('multiple_tree');
        }

        $command->createTable(
            'tree',
            [
                'id' => $this->primaryKey()->notNull(),
                'name' => $this->text()->notNull(),
                'lft' => $this->integer()->notNull(),
                'rgt' => $this->integer()->notNull(),
                'depth' => $this->integer()->notNull(),
            ],
        )->execute();

        $command->createTable(
            'multiple_tree',
            [
                'id' => $this->primaryKey()->notNull(),
                'tree' => $this->integer(),
                'name' => $this->text()->notNull(),
                'lft' => $this->integer()->notNull(),
                'rgt' => $this->integer()->notNull(),
                'depth' => $this->integer()->notNull(),
            ],
        )->execute();
    }

    /**
     * @phpstan-return list<
     *   array{
     *     id: int,
     *     name: string,
     *     tree: int,
     *     type: string,
     *     lft: int,
     *     rgt: int,
     *     depth: int
     *   }
     * >
     */
    protected function getDataSet(): array
    {
        $dataSetTree = Tree::find()->asArray()->all();

        foreach ($dataSetTree as $key => $value) {
            $dataSetTree[$key]['type'] = 'tree';
            $dataSetTree[$key]['tree'] = 0;
        }

        $dataSetMultipleTree = MultipleTree::find()->asArray()->all();

        foreach ($dataSetMultipleTree as $key => $value) {
            $dataSetMultipleTree[$key]['type'] = 'multiple_tree';
        }

        return array_merge($dataSetTree, $dataSetMultipleTree);
    }

    /**
     * @phpstan-return list<
     *   array{
     *     id: int,
     *     name: string,
     *     tree: int,
     *     type: 'multiple_tree',
     *     lft: int,
     *     rgt: int,
     *     depth: int,
     *   }
     * >
     */
    protected function getDataSetMultipleTree(): array
    {
        $dataSetMultipleTree = MultipleTree::find()->asArray()->all();

        foreach ($dataSetMultipleTree as $key => $value) {
            $dataSetMultipleTree[$key]['type'] = 'multiple_tree';
        }

        return array_values($dataSetMultipleTree);
    }

    protected function generateFixtureTree(): void
    {
        $this->createDatabase();

        $command = $this->getDb()->createCommand();

        // Carga el XML en la tabla `tree`
        $xml = new SimpleXMLElement(__DIR__ . '/Support/data/test.xml', 0, true);

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

    protected function mockConsoleApplication(): void
    {
        new Application(
            [
                'id' => 'testapp',
                'basePath' => dirname(__DIR__),
                'components' => [
                    'db' => [
                        'class' => Connection::class,
                        'dsn' => 'sqlite::memory:',
                    ],
                ],
            ],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
