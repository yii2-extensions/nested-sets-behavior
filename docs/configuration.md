# Configuration reference

## Overview

This guide covers all configuration options for the Yii Nested Sets Behavior, from basic setup to advanced hierarchical
data management scenarios.

## Basic configuration

### Minimal setup

```php
<?php

declare(strict_types=1);

use yii2\extensions\nestedsets\NestedSetsBehavior;

return [
    'behaviors' => [
        'nestedSets' => NestedSetsBehavior::class,
    ],
];
```

### Standard model configuration

```php
<?php

declare(strict_types=1);

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * @property int $depth
 * @property int $id
 * @property int $lft
 * @property int $rgt
 */
class Category extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'nestedSets' => [
                'class' => NestedSetsBehavior::class,
                'leftAttribute' => 'lft',
                'rightAttribute' => 'rgt',
                'depthAttribute' => 'depth',
                // 'treeAttribute' => 'tree', // Enable for multiple trees
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

## Attribute configuration

### Core nested sets attributes

Configure the database field names used by the behavior.

```php
'behaviors' => [
    'nestedSets' => [
        'class' => NestedSetsBehavior::class,
        'leftAttribute' => 'lft',    // Left boundary field
        'rightAttribute' => 'rgt',   // Right boundary field
        'depthAttribute' => 'depth', // Depth/level field
    ],
],
```

### Custom attribute names

Use custom field names if your database schema differs.

```php
'behaviors' => [
    'nestedSets' => [
        'class' => NestedSetsBehavior::class,
        'leftAttribute' => 'left_boundary',
        'rightAttribute' => 'right_boundary',
        'depthAttribute' => 'tree_level',
    ],
],
```

### Multiple trees support

Enable multiple independent trees in the same table.

```php
'behaviors' => [
    'nestedSets' => [
        'class' => NestedSetsBehavior::class,
        'treeAttribute' => 'tree',   // Field to distinguish trees
        'leftAttribute' => 'lft',
        'rightAttribute' => 'rgt',
        'depthAttribute' => 'depth',
    ],
],
```

## Query behavior configuration

Add enhanced query capabilities to your models.

### Basic query behavior

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
```

### Integration with model

```php
<?php

declare(strict_types=1);

class Category extends ActiveRecord
{
    // ... behavior configuration ...

    /**
     * @return CategoryQuery<static>
     */
    public static function find(): CategoryQuery
    {
        return new CategoryQuery(static::class);
    }
}
```

## Database schema configurations

### Single tree schema

The SQL schemas below use MySQL syntax. For portable schema creation, use the package migrations described in the
[database setup](../README.md#database-setup), including the lock registry.

For applications with one tree per table.

```sql
CREATE TABLE category (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    lft INT NOT NULL,
    rgt INT NOT NULL,
    depth INT NOT NULL,

    INDEX idx_lft_rgt (lft, rgt),
    INDEX idx_depth (depth)
);
```

### Multiple trees schema

For applications with multiple independent trees.

```sql
CREATE TABLE category (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tree INT,
    name VARCHAR(255) NOT NULL,
    lft INT NOT NULL,
    rgt INT NOT NULL,
    depth INT NOT NULL,

    INDEX idx_tree_lft_rgt (tree, lft, rgt),
    INDEX idx_tree_depth (tree, depth)
);
```

### Custom schema with different field names

```sql
CREATE TABLE hierarchy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tree_id INT,
    title VARCHAR(255) NOT NULL,
    left_boundary INT NOT NULL,
    right_boundary INT NOT NULL,
    tree_level INT NOT NULL,

    INDEX idx_tree_boundaries (tree_id, left_boundary, right_boundary),
    INDEX idx_tree_level (tree_id, tree_level)
);
```

Corresponding behavior configuration.

```php
'behaviors' => [
    'nestedSets' => [
        'class' => NestedSetsBehavior::class,
        'treeAttribute' => 'tree_id',
        'leftAttribute' => 'left_boundary',
        'rightAttribute' => 'right_boundary',
        'depthAttribute' => 'tree_level',
    ],
],
```

## Transaction configuration

### Enable transactions

Ensure data integrity during tree operations.

```php
public function transactions(): array
{
    return [
        self::SCENARIO_DEFAULT => self::OP_ALL,
    ];
}
```

`makeRoot()`, `appendTo()`, `prependTo()`, `insertBefore()`, `insertAfter()` and `deleteWithChildren()` open a
transaction themselves, roll back on `false` or an exception, and clear the pending operation. A failed call restores
the owner's attributes and new-record state so it can be corrected and retried. These methods reload the stored
coordinates of the owner and the target before writing, keep unsaved application attributes, and raise
`yii\db\StaleObjectException` when the owner or the target no longer exists or when the owner's optimistic lock changed.
The target must use the same `Connection` instance and `tableName()` result as the owner.

An ordinary `delete()` is a structural operation: keep the configuration above, or at least `OP_DELETE`, for every
writable scenario, or begin an explicit Yii transaction; without one, `yii\base\InvalidConfigException` is thrown before
anything is written. An ordinary `save()` that changes only application attributes needs no transaction. Nested
operations require savepoint support (`Connection::$enableSavepoint`); commit and roll back through Yii, not through
PDO. On MySQL, the model table and the lock table must use InnoDB.

After rolling back an outer transaction, call `refresh()` on the ActiveRecord instances loaded inside it and recreate
the instances inserted in it. Other loaded instances may hold old coordinates; call `refresh()` before reading their
state or running tree queries. The structural methods reload their own coordinates.

### Concurrent writers

Writers of the same physical table are serialized through a row of the `nested_sets_lock` table created by
`m261016_000001_nested_sets_lock`. The table is unprefixed and must exist in the same database and schema as the model
table; copy and qualify the migration for tables outside the connection's default schema. The application connection
needs `SELECT`, `INSERT` and `UPDATE` on it and read access to the driver's catalog views. Runtime operations never
create tables.

Structural calls acquire the lock before validation and coordinate reads; `delete()` acquires it in its before-delete
handler. A successful nested call keeps the lock until the outermost transaction ends. Rolling back to an earlier
savepoint can release locks acquired after that savepoint; each structural call acquires the lock again. All trees in
one table share the lock, including cross-tree moves. Registered tables use separate lock rows, but database locking
rules still apply: SQLite serializes writers for the entire database file. The reserved `_bootstrap` row serializes the
first registration of a table. Do not edit the rows.

`lockTimeout` (seconds, `1` through `3600`, default `10`) bounds each wait for the lock row and restores the previous
connection setting afterwards. It does not bound metadata loading, application callbacks or later SQL. Supported storage
is a physical base table: views, synonyms and SQLite attached databases are rejected.

MySQL coordinate reads are locking current reads. PostgreSQL, Oracle and SQL Server may instead reject a write from an
older snapshot with a serialization conflict. Catch lock, deadlock and serialization exceptions by type, roll back the
complete outer transaction and retry with freshly loaded models. After a server-side rollback the behavior clears Yii's
transaction levels; never commit that unit of work.

Code that reads tree state to decide a later write, or that writes with raw SQL, `updateAll()` or `deleteAll()`, must
acquire the same lock inside its transaction first. Bypass query caching for decision reads and acquire the lock before
establishing a read snapshot; acquiring it does not refresh an older application snapshot:

```php
$transaction = Category::getDb()->beginTransaction();

try {
    (new Category())->acquireTreeLock();
    // Read current application state and perform tree operations here.
    $transaction->commit();
} catch (\Throwable $exception) {
    $transaction->rollBack();

    throw $exception;
}
```

`acquireTreeLock()` requires an active Yii transaction and uses the behavior's `lockTimeout`; it may be called
repeatedly in one transaction. Acquiring it at the start of a transaction that mixes ordinary saves with structural
operations gives cooperating writers the same lock order; deadlocks from other application locks remain possible. Stop
all writers before schema changes and deployments, because releases before 0.2.0 do not acquire the lock. Applications
that write several model tables in one transaction should acquire their locks in a consistent order.

## Validation configuration

### Basic validation rules

```php
public function rules(): array
{
    return [
        ['name', 'required'],
        ['name', 'string', 'max' => 255],
        // IMPORTANT: Do NOT add validation rules for nested sets fields
        // lft, rgt, depth, tree are managed automatically
    ];
}
```

### Validation with additional fields

```php
public function rules(): array
{
    return [
        [['name', 'description'], 'required'],
        ['name', 'string', 'max' => 255],
        ['description', 'string'],
        ['status', 'in', 'range' => ['active', 'inactive']],
        ['sort_order', 'integer', 'min' => 0],
        // Nested sets fields are excluded from validation
    ];
}
```

## Performance optimization

### Database indexes

Critical indexes for optimal performance.

```sql
-- Single tree indexes
CREATE INDEX idx_lft_rgt ON table_name (lft, rgt);
CREATE INDEX idx_depth ON table_name (depth);

-- Multiple trees indexes
CREATE INDEX idx_tree_lft_rgt ON table_name (tree, lft, rgt);
CREATE INDEX idx_tree_depth ON table_name (tree, depth);

-- Composite indexes for complex queries
CREATE INDEX idx_tree_depth_lft ON table_name (tree, depth, lft);
```

### Query optimization

Use appropriate query methods for different scenarios.

```php
// Efficient: Get direct children only
$directChildren = $node->children(1)->all();

// Less efficient: Get all descendants then filter
$allDescendants = $node->children()->all();
$directChildren = array_filter($allDescendants, static fn($child) => $child->depth === $node->depth + 1);
```

## Advanced configuration

### Custom behavior extension

Extend the behavior for additional functionality.

```php
<?php

declare(strict_types=1);

namespace app\behaviors;

use yii2\extensions\nestedsets\NestedSetsBehavior;

class CustomNestedSetsBehavior extends NestedSetsBehavior
{
    public $sortAttribute = 'sort_order';

    public function findChildrenSorted(): array
    {
        return $this->owner
            ->children()
            ->orderBy([$this->sortAttribute => SORT_ASC])
            ->all();
    }
}
```

### Event handling

Handle nested sets events in your model.

```php
public function init(): void
{
    parent::init();

    $this->on(ActiveRecord::EVENT_AFTER_INSERT, [$this, 'afterNestedInsert']);
    $this->on(ActiveRecord::EVENT_AFTER_UPDATE, [$this, 'afterNestedUpdate']);
    $this->on(ActiveRecord::EVENT_AFTER_DELETE, [$this, 'afterNestedDelete']);
}

public function afterNestedInsert($event): void
{
    // Custom logic after node insertion
    $this->updateCacheCounters();
}

public function afterNestedUpdate($event): void
{
    // Custom logic after node movement
    $this->refreshRelatedData();
}

public function afterNestedDelete($event): void
{
    // Custom logic after node deletion
    $this->cleanupOrphanedData();
}
```

### Integration with other behaviors

Combine with other Yii behaviors.

```php
public function behaviors(): array
{
    return [
        'timestamp' => [
            'class' => TimestampBehavior::class,
            'createdAtAttribute' => 'created_at',
            'updatedAtAttribute' => 'updated_at',
        ],
        'nestedSets' => [
            'class' => NestedSetsBehavior::class,
            'treeAttribute' => 'tree',
        ],
        'sluggable' => [
            'class' => SluggableBehavior::class,
            'attribute' => 'name',
        ],
    ];
}
```

### Common configuration errors

Avoid these common mistakes:

```php
// ❌ Wrong: Adding validation for nested sets fields
public function rules(): array
{
    return [
        ['lft', 'integer'], // Don't validate these fields
        ['rgt', 'integer'], // They're managed automatically
        ['depth', 'integer'], // Behavior handles these
        ['tree', 'integer'],
    ];
}

// ✅ Correct: Only validate your business fields
public function rules(): array
{
    return [
        ['name', 'required'],
        ['name', 'string', 'max' => 255],
        // Nested sets fields are excluded
    ];
}

// ❌ Wrong: Calling delete() without model transactions or an explicit transaction
public function behaviors(): array
{
    return [
        'nestedSets' => NestedSetsBehavior::class,
        // An ordinary delete() requires transactions() or an active Yii transaction
    ];
}

// ✅ Correct: Enable model transactions for ordinary delete() too
public function behaviors(): array
{
    return [
        'nestedSets' => NestedSetsBehavior::class,
    ];
}

public function transactions(): array
{
    return [
        self::SCENARIO_DEFAULT => self::OP_ALL,
    ];
}
```

## Migration configurations

### Adding nested sets to existing table

Add nested sets fields to an existing table, making each row an independent root. Enable `treeAttribute` for this
schema. Stop application writers during the migration, apply the lock registry migration, and configure the model before
resuming writes.

```php
<?php

declare(strict_types=1);

use yii\db\Migration;

class m240101_000000_add_nested_sets_to_category extends Migration
{
    public function safeUp(): void
    {
        // Add nested sets columns
        $this->addColumn('{{%category}}', 'lft', $this->integer()->notNull()->defaultValue(1));
        $this->addColumn('{{%category}}', 'rgt', $this->integer()->notNull()->defaultValue(2));
        $this->addColumn('{{%category}}', 'depth', $this->integer()->notNull()->defaultValue(0));

        // Each existing record starts in its own tree
        $this->addColumn('{{%category}}', 'tree', $this->integer());

        // Add performance indexes
        $this->createIndex('idx_category_lft_rgt', '{{%category}}', ['lft', 'rgt']);
        $this->createIndex('idx_category_depth', '{{%category}}', ['depth']);
        $this->createIndex('idx_category_tree', '{{%category}}', ['tree']);

        // Initialize existing records as root nodes
        $this->execute("
            UPDATE {{%category}}
            SET tree = id, lft = 1, rgt = 2, depth = 0
        ");
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_category_tree', '{{%category}}');
        $this->dropIndex('idx_category_depth', '{{%category}}');
        $this->dropIndex('idx_category_lft_rgt', '{{%category}}');

        $this->dropColumn('{{%category}}', 'tree');
        $this->dropColumn('{{%category}}', 'depth');
        $this->dropColumn('{{%category}}', 'rgt');
        $this->dropColumn('{{%category}}', 'lft');
    }
}
```

### Converting from adjacency list

Convert the complete adjacency list before making the new columns mandatory. A migration that only initializes roots
leaves descendants without coordinates and cannot establish a valid nested set.

1. Lock writes and snapshot every node's primary key and parent key.
2. Reject missing parents, cycles, unreachable nodes, and multiple roots when `treeAttribute` is disabled.
3. Traverse each root depth first. Assign consecutive left and right values from `1` and derive depth from the parent.
   With multiple trees, assign the root's primary key to every node's tree attribute.
4. Apply parameterized updates in a transaction and verify contiguous, unique boundaries, strict containment, depth, and
   exactly one root per tree before adding `NOT NULL` constraints and indexes.
5. Keep `parent_id` until the converted hierarchy has been verified. On databases whose DDL commits implicitly, prepare
   schema changes separately from the transactional data conversion.

[Back to documentation](../README.md#documentation)
