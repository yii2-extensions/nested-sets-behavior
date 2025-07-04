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
 * @phpstan-property int $depth
 * @phpstan-property int $id
 * @phpstan-property int $lft
 * @phpstan-property int $rgt
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
     * @phpstan-return CategoryQuery<static>
     */
    public static function find(): CategoryQuery
    {
        return new CategoryQuery(static::class);
    }
}
```

## Database schema configurations

### Single tree schema

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

### Selective transaction control

Enable transactions only for specific operations.

```php
public function transactions(): array
{
    return [
        self::SCENARIO_DEFAULT => self::OP_INSERT | self::OP_UPDATE | self::OP_DELETE,
    ];
}
```

### Disable transactions for specific operations

```php
public function isTransactional($operation): bool
{
    if ($operation === ActiveRecord::OP_DELETE) {
        return false; // Disable transactions for delete operations
    }
    
    return parent::isTransactional($operation);
}
```

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
        return $this->getOwner()
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

// ❌ Wrong: Missing transactions configuration
public function behaviors(): array
{
    return [
        'nestedSets' => NestedSetsBehavior::class,
        // Missing transactions() method
    ];
}

// ✅ Correct: Enable transactions for data integrity
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

Add nested sets fields to an existing table.

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
        
        // Add tree column for multiple trees (optional)
        $this->addColumn('{{%category}}', 'tree', $this->integer());
        
        // Add performance indexes
        $this->createIndex('idx_category_lft_rgt', '{{%category}}', ['lft', 'rgt']);
        $this->createIndex('idx_category_depth', '{{%category}}', ['depth']);
        $this->createIndex('idx_category_tree', '{{%category}}', ['tree']);
        
        // Initialize existing records as root nodes
        $this->execute("
            UPDATE {{%category}} 
            SET tree = id, lft = 1, rgt = 2, depth = 0
            WHERE lft IS NULL OR lft = 0
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

Migrate from parent_id structure to nested sets.

```php
<?php

declare(strict_types=1);

use yii\db\Migration;

class m240101_000000_convert_to_nested_sets extends Migration
{
    public function safeUp(): void
    {
        // Add nested sets columns
        $this->addColumn('{{%category}}', 'lft', $this->integer());
        $this->addColumn('{{%category}}', 'rgt', $this->integer());
        $this->addColumn('{{%category}}', 'depth', $this->integer());
        
        // Convert adjacency list to nested sets
        $this->convertAdjacencyToNestedSets();
        
        // Make columns NOT NULL after conversion
        $this->alterColumn('{{%category}}', 'lft', $this->integer()->notNull());
        $this->alterColumn('{{%category}}', 'rgt', $this->integer()->notNull());
        $this->alterColumn('{{%category}}', 'depth', $this->integer()->notNull());
        
        // Add indexes
        $this->createIndex('idx_category_lft_rgt', '{{%category}}', ['lft', 'rgt']);
        $this->createIndex('idx_category_depth', '{{%category}}', ['depth']);
        
        // Drop old parent_id column (optional)
        // $this->dropColumn('{{%category}}', 'parent_id');
    }
    
    private function convertAdjacencyToNestedSets(): void
    {
        // This is a simplified conversion - you may need more complex logic
        $this->execute("
            -- Set root nodes
            UPDATE {{%category}} 
            SET lft = 1, rgt = 2, depth = 0 
            WHERE parent_id IS NULL;
            
            -- You'll need a recursive procedure or application logic
            -- to properly convert the entire tree structure
        ");
    }
}
```

## Next steps

- 💡 [Usage Examples](examples.md)
- 🧪 [Testing Guide](testing.md)
