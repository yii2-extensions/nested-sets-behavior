<?php

declare(strict_types=1);

namespace Yii2\Extensions\NestedSets\Tests;

use SimpleXMLElement;
use Yii;
use yii\console\Application;
use yii\db\Connection;
use yii\db\SchemaBuilderTrait;
use yii\di\Container;
use Yii2\Extensions\NestedSets\Tests\Support\Model\MultipleTree;
use Yii2\Extensions\NestedSets\Tests\Support\Model\Tree;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use SchemaBuilderTrait;

    public function getDb(): Connection
    {
        return Yii::$app->getDb();
    }

    protected function buildFlatXMLDataSet(array $dataSet): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><dataset></dataset>');

        foreach ($dataSet as $item) {
            $treeElement = $xml->addChild($item['type']);
            $treeElement->addAttribute('id', $item['id']);

            if ($item['type'] === 'multiple_tree') {
                $treeElement->addAttribute('tree', $item['tree']);
            }

            $treeElement->addAttribute('lft', $item['lft']);
            $treeElement->addAttribute('rgt', $item['rgt']);
            $treeElement->addAttribute('depth', $item['depth']);
            $treeElement->addAttribute('name', $item['name']);
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        $formattedXml = $dom->saveXML();

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

    protected function destroyApplication()
    {
        Yii::$app = null;
        Yii::$container = new Container();
    }

    protected function getDataSet(): array
    {
        $dataSetTree = Tree::find()->asArray()->all();

        foreach ($dataSetTree as $key => $value) {
            $dataSetTree[$key]['type'] = 'tree';
        }

        $dataSetMultipleTree = MultipleTree::find()->asArray()->all();

        foreach ($dataSetMultipleTree as $key => $value) {
            $dataSetMultipleTree[$key]['type'] = 'multiple_tree';
        }

        return array_merge($dataSetTree, $dataSetMultipleTree);
    }

    protected function getDataSetMultipleTree(): array
    {
        $dataSetMultipleTree = MultipleTree::find()->asArray()->all();

        foreach ($dataSetMultipleTree as $key => $value) {
            $dataSetMultipleTree[$key]['type'] = 'multiple_tree';
        }

        return $dataSetMultipleTree;
    }

    protected function generateFixtureTree(): void
    {
        $this->createDatabase();

        $command = $this->getDb()->createCommand();

        // Carga el XML en la tabla `tree`
        $xml = new SimpleXMLElement(__DIR__ . '/Support/data/test.xml', 0, true);

        foreach ($xml->children() as $element => $treeElement) {
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

    protected function setup(): void
    {
        parent::setUp();
        $this->mockConsoleApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->destroyApplication();
    }
}
