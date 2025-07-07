<p align="center">
    <a href="https://github.com/yii2-extensions/nested-sets-behavior" target="_blank">
        <img src="https://www.yiiframework.com/image/yii_logo_light.svg" alt="Yii Framework">
    </a>
    <h1 align="center">Nested sets behavior</h1>
    <br>
</p>

<p align="center">
    <a href="https://www.php.net/releases/8.1/en.php" target="_blank">
        <img src="https://img.shields.io/badge/PHP-%3E%3D8.1-787CB5" alt="PHP Version">
    </a>
    <a href="https://github.com/yiisoft/yii2/tree/2.0.53" target="_blank">
        <img src="https://img.shields.io/badge/Yii2%20-2.0.53-blue" alt="Yii2 2.0.53">
    </a>
    <a href="https://github.com/yiisoft/yii2/tree/22.0" target="_blank">
        <img src="https://img.shields.io/badge/Yii2%20-22-blue" alt="Yii2 22.0">
    </a>
    <a href="https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/build.yml" target="_blank">
        <img src="https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/build.yml/badge.svg" alt="PHPUnit">
    </a>
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/yii2-extensions/nested-sets-behavior/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyii2-extensions%2Fnested-sets-behavior%2Fmain" alt="Mutation Testing">
    </a>    
    <a href="https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/static.yml" target="_blank">        
        <img src="https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/static.yml/badge.svg" alt="Static Analysis">
    </a>  
</p>

A powerful behavior for managing hierarchical data structures using the nested sets pattern in Yii ActiveRecord models.

Efficiently store and query tree structures like categories, menus, organizational charts, and any hierarchical data
with high-performance database operations.

## Features

- ✅ **Efficient Tree Operations** - Insert, move, delete nodes with automatic boundary management.
- ✅ **Flexible Queries** - Find ancestors, descendants, siblings, leaves, and roots.
- ✅ **Multiple Trees Support** - Manage multiple independent trees in the same table.
- ✅ **Query Optimization** - Single-query operations for maximum performance.
- ✅ **Transaction Safety** - All operations are wrapped in database transactions.
- ✅ **Validation & Error Handling** - Comprehensive validation with clear error messages.

## Database support

[![Microsoft SQL Server](https://img.shields.io/badge/Microsoft%20SQL%20Server-CC2927?style=for-the-badge&logo=microsoft%20sql%20server&logoColor=white)](https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/build-mssql.yml)
[![MySQL](https://img.shields.io/badge/mysql-4479A1.svg?style=for-the-badge&logo=mysql&logoColor=white)](https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/build-mysql.yml)
[![Oracle](https://img.shields.io/badge/Oracle-F80000?style=for-the-badge&logo=oracle&logoColor=white)](https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/build-oracle.yml)
[![PostgreSQL](https://img.shields.io/badge/postgresql-4169e1?style=for-the-badge&logo=postgresql&logoColor=white)](https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/build-pgsql.yml)
[![SQLite](https://img.shields.io/badge/sqlite-003B57.svg?style=for-the-badge&logo=sqlite&logoColor=white)](https://github.com/yii2-extensions/nested-sets-behavior/actions/workflows/build.yml)

## Quick start

### Installation

```bash
composer require yii2-extensions/nested-sets-behavior
```

### How it works

The nested sets model is a technique for storing hierarchical data in a relational database. Unlike adjacency lists
(parent_id approach), nested sets enable efficient tree operations with minimal database queries.

1. **Creates root nodes** using the nested sets pattern with `lft`, `rgt`, and `depth` fields.
2. **Manages hierarchy** automatically when inserting, moving, or deleting nodes.
3. **Optimizes queries** using boundary values for efficient tree traversal.
4. **Supports transactions** to ensure data integrity during complex operations.

#### Why nested sets?

- **Fast queries**: Get all descendants with a single query (`lft BETWEEN parent.lft AND parent.rgt`).
- **Efficient tree operations**: No recursive queries needed for tree traversal.
- **Automatic maintenance**: Left/right boundaries are calculated automatically.
- **Depth tracking**: Easy to limit query depth or build breadcrumbs.

```text
Example tree structure:
Electronics (1,12,0)
├── Mobile Phones (2,7,1)
│   └── Smartphones (3,6,2)
│       └── iPhone (4,5,3)
└── Computers (8,11,1)
    └── Laptops (9,10,2)

Numbers represent: (left, right, depth)
```

### Database setup

The package includes ready-to-use migrations for creating the necessary database structure.

#### Quick setup (Recommended)

1. **Configure console application**:
```php
<?php

declare(strict_types=1);

use yii\console\controllers\MigrateController;

// console/config/main.php
return [
    'controllerMap' => [
        'migrate' => [
            'class' => MigrateController::class,
            'migrationPath' => [
                '@console/migrations',
                '@vendor/yii2-extensions/nested-sets-behavior/migrations',
            ],
        ],
    ],
];
```

2. **Run migrations**:
```bash
# For single tree structure
./yii migrate/up m250707_103609_tree

# For multiple trees structure  
./yii migrate/up m250707_104009_multiple_tree
```

#### Alternative: Direct migration execution

```bash
# Run without configuration changes
./yii migrate/up --migrationPath=@vendor/yii2-extensions/nested-sets-behavior/migrations
```

#### Table structures created

**Single tree** (`m250707_103609_tree.php`). Creates a `tree` table for single hierarchical structure.

```sql
CREATE TABLE tree (
  id INTEGER NOT NULL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  lft INTEGER NOT NULL,
  rgt INTEGER NOT NULL,
  depth INTEGER NOT NULL
);

CREATE INDEX idx_tree_lft ON tree (lft);
CREATE INDEX idx_tree_rgt ON tree (rgt);
CREATE INDEX idx_tree_depth ON tree (depth);
CREATE INDEX idx_tree_lft_rgt ON tree (lft, rgt);
```

**Multiple trees** (`m250707_104009_multiple_tree.php`). Creates a `multiple_tree` table for multiple independent trees.

```sql
CREATE TABLE multiple_tree (
  id INTEGER NOT NULL PRIMARY KEY,
  tree INTEGER DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  lft INTEGER NOT NULL,
  rgt INTEGER NOT NULL,
  depth INTEGER NOT NULL
);

CREATE INDEX idx_multiple_tree_tree ON multiple_tree (tree);
CREATE INDEX idx_multiple_tree_lft ON multiple_tree (lft);
CREATE INDEX idx_multiple_tree_rgt ON multiple_tree (rgt);
CREATE INDEX idx_multiple_tree_depth ON multiple_tree (depth);
CREATE INDEX idx_multiple_tree_tree_lft_rgt ON multiple_tree (tree, lft, rgt);
```

### Basic Configuration

Add the behavior to your ActiveRecord model.

```php
<?php

declare(strict_types=1);

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * @phpstan-property int $depth
 * @phpstan-property int $id
 * @phpstan-property int $lft
 * @phpstan-property int $rgt
 */
class Category extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%category}}';
    }

    public function behaviors(): array
    {
        return [
            'nestedSets' => [
                'class' => NestedSetsBehavior::class,
                // 'treeAttribute' => 'tree', // Enable for multiple trees
                // 'leftAttribute' => 'lft',   // Default: 'lft'
                // 'rightAttribute' => 'rgt',  // Default: 'rgt'
                // 'depthAttribute' => 'depth', // Default: 'depth'
            ],
        ];
    }

    public function transactions(): array
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}
```

### Basic Usage

#### Creating and building trees

```php
<?php

declare(strict_types=1);

// Create root node
$root = new Category(['name' => 'Electronics']);
$root->makeRoot();

// Add children
$phones = new Category(['name' => 'Mobile Phones']);
$phones->appendTo($root);

$computers = new Category(['name' => 'Computers']);
$computers->appendTo($root);

// Add grandchildren
$smartphone = new Category(['name' => 'Smartphones']);
$smartphone->appendTo($phones);

$laptop = new Category(['name' => 'Laptops']);
$laptop->appendTo($computers);
```

#### Querying the tree

```php
<?php

declare(strict_types=1);

// Get all descendants of a node
$children = $root->children()->all();

// Get only direct children
$directChildren = $root->children(1)->all();

// Get all ancestors of a node
$parents = $smartphone->parents()->all();

// Get all leaf nodes (nodes without children)
$leaves = $root->leaves()->all();

// Navigate siblings
$nextSibling = $phones->next()->one();
$prevSibling = $computers->prev()->one();
```

#### Moving nodes

```php
<?php

declare(strict_types=1);

// Move as last child
$smartphone->appendTo($computers);

// Move as first child  
$smartphone->prependTo($phones);

// Move as next sibling
$smartphone->insertAfter($laptop);

// Move as previous sibling
$smartphone->insertBefore($laptop);

// Make node a new root (multiple trees only)
$smartphone->makeRoot();
```

#### Deleting nodes

```php
<?php

declare(strict_types=1);

// Delete node only (children become children of parent)
$phones->delete();

// Delete node with all descendants
$phones->deleteWithChildren();
```

### Query builder integration

Add query behavior for advanced tree queries.

```php
<?php

declare(strict_types=1);

use yii\db\ActiveQuery;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;

/**
 * @template T of Category
 *
 * @extends ActiveQuery<T>
 */
class CategoryQuery extends ActiveQuery
{
    public function behaviors(): array
    {
        return [
            'nestedSetsQuery' => NestedSetsQueryBehavior::class,
        ];
    }
}

// In your Category model
/**
 * @phpstan-return CategoryQuery<static>
 */
public static function find(): CategoryQuery
{
    return new CategoryQuery(static::class);
}
```

Now you can use enhanced queries.

```php
<?php

declare(strict_types=1);

// Find all root nodes
$roots = Category::find()->roots()->all();

// Find all leaf nodes  
$leaves = Category::find()->leaves()->all();
```

## Documentation

For detailed configuration options and advanced usage.

- 📚 [Installation Guide](docs/installation.md)
- ⚙️ [Configuration Reference](docs/configuration.md) 
- 💡 [Usage Examples](docs/examples.md)
- 🧪 [Testing Guide](docs/testing.md)

## Quality code

[![Latest Stable Version](https://poser.pugx.org/yii2-extensions/nested-sets-behavior/v)](https://github.com/yii2-extensions/nested-sets-behavior/releases)
[![Total Downloads](https://poser.pugx.org/yii2-extensions/nested-sets-behavior/downloads)](https://packagist.org/packages/yii2-extensions/nested-sets-behavior)
[![codecov](https://codecov.io/gh/yii2-extensions/nested-sets-behavior/graph/badge.svg?token=Upc4yA23YN)](https://codecov.io/gh/yii2-extensions/nested-sets-behavior)
[![phpstan-level](https://img.shields.io/badge/PHPStan%20level-max-blue)](https://github.com/yii2-extensions/localeurls/actions/workflows/static.yml)
[![style-ci](https://github.styleci.io/repos/717718161/shield?branch=main)](https://github.styleci.io/repos/717718161?branch=main)

## Our social networks

[![X](https://img.shields.io/badge/follow-@terabytesoftw-1DA1F2?logo=x&logoColor=1DA1F2&labelColor=555555?style=flat)](https://x.com/Terabytesoftw)

## License

[![License](https://poser.pugx.org/yii2-extensions/nested-sets-behavior/license)](LICENSE.md)

## Fork 

This package is a fork of [https://github.com/creocoder/yii2-nested-sets](https://github.com/creocoder/yii2-nested-sets) with some corrections.
