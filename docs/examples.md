# Usage examples

This document provides comprehensive examples of how to use the Yii Nested Sets Behavior in real-world hierarchical data
management scenarios.

## Building tree structures

### Creating root nodes

```php
<?php

declare(strict_types=1);

use app\models\Category;

// Create a single root for the entire tree
$root = new Category(['name' => 'All Categories']);
$root->makeRoot();

echo "Root created: ID={$root->id}, Left={$root->lft}, Right={$root->rgt}, Depth={$root->depth}\n";
// Output: Root created: ID=1, Left=1, Right=2, Depth=0
```

### Creating multiple tree roots

```php
<?php

declare(strict_types=1);

// For models with treeAttribute enabled
$electronicsRoot = new Category(['name' => 'Electronics']);
$electronicsRoot->makeRoot();

$clothingRoot = new Category(['name' => 'Clothing']);
$clothingRoot->makeRoot();

$homeRoot = new Category(['name' => 'Home & Garden']);
$homeRoot->makeRoot();

// Each root has its own tree identifier
echo "Electronics tree: {$electronicsRoot->tree}\n";
echo "Clothing tree: {$clothingRoot->tree}\n";
echo "Home tree: {$homeRoot->tree}\n";
```

### Building a complete category hierarchy

```php
<?php

declare(strict_types=1);

// Start with a root
$root = new Category(['name' => 'Store Categories']);
$root->makeRoot();

// Add main categories
$electronics = new Category(['name' => 'Electronics']);
$electronics->appendTo($root);

$clothing = new Category(['name' => 'Clothing']);
$clothing->appendTo($root);

// Add subcategories to Electronics
$phones = new Category(['name' => 'Mobile Phones']);
$phones->appendTo($electronics);

$computers = new Category(['name' => 'Computers']);
$computers->appendTo($electronics);

// Add sub-subcategories
$smartphones = new Category(['name' => 'Smartphones']);
$smartphones->appendTo($phones);

$featurePhones = new Category(['name' => 'Feature Phones']);
$featurePhones->appendTo($phones);

$laptops = new Category(['name' => 'Laptops']);
$laptops->appendTo($computers);

$desktops = new Category(['name' => 'Desktop Computers']);
$desktops->appendTo($computers);

// Result tree structure:
// Store Categories (1,16)
// ├── Electronics (2,13)
// │   ├── Mobile Phones (3,8)
// │   │   ├── Smartphones (4,5)
// │   │   └── Feature Phones (6,7)
// │   └── Computers (9,12)
// │       ├── Laptops (10,11)
// │       └── Desktop Computers (12,13)
// └── Clothing (14,15)
```

## Inserting and positioning nodes

### Adding nodes as children

```php
<?php

declare(strict_types=1);

// Append as last child
$accessories = new Category(['name' => 'Accessories']);
$accessories->appendTo($electronics); // Becomes last child

// Prepend as first child  
$tablets = new Category(['name' => 'Tablets']);
$tablets->prependTo($electronics); // Becomes first child

// Result: Electronics now has Tablets, Mobile Phones, Computers, Accessories
```

### Inserting nodes as siblings

```php
<?php

declare(strict_types=1);

// Insert after a specific node
$gaming = new Category(['name' => 'Gaming']);
$gaming->insertAfter($computers); // Places Gaming after Computers

// Insert before a specific node
$audio = new Category(['name' => 'Audio']);
$audio->insertBefore($phones); // Places Audio before Mobile Phones

// Result: Electronics now has Audio, Mobile Phones, Computers, Gaming, Accessories
```

### Positioning nodes with specific ordering

```php
<?php

declare(strict_types=1);

class ProductController extends Controller
{
    public function actionOrganizeCategories(): void
    {
        $electronics = Category::findOne(['name' => 'Electronics']);
        
        // Create categories in desired order
        $categories = [
            'Computers',
            'Mobile Phones', 
            'Audio',
            'Gaming',
            'Accessories'
        ];
        
        $previousCategory = null;

        foreach ($categories as $categoryName) {
            $category = new Category(['name' => $categoryName]);
            
            if ($previousCategory === null) {
                // First category becomes first child
                $category->prependTo($electronics);
            } else {
                // Subsequent categories inserted after previous
                $category->insertAfter($previousCategory);
            }
            
            $previousCategory = $category;
        }
    }
}
```

## Querying tree data

### Finding ancestors and descendants

```php
<?php

declare(strict_types=1);

// Get all ancestors of a node (breadcrumb path)
$smartphone = Category::findOne(['name' => 'Smartphones']);
$breadcrumbs = $smartphone->parents()->all();

foreach ($breadcrumbs as $ancestor) {
    echo "{$ancestor->name} > ";
}

echo $smartphone->name;
// Output: Store Categories > Electronics > Mobile Phones > Smartphones

// Get all descendants of a node
$electronics = Category::findOne(['name' => 'Electronics']);
$allDescendants = $electronics->children()->all();

echo "Electronics has " . count($allDescendants) . " descendants:\n";

foreach ($allDescendants as $descendant) {
    echo str_repeat('  ', $descendant->depth - $electronics->depth) . $descendant->name . "\n";
}
```

### Finding direct children and parents

```php
<?php

declare(strict_types=1);

// Get only direct children (depth = current depth + 1)
$electronics = Category::findOne(['name' => 'Electronics']);
$directChildren = $electronics->children(1)->all();

echo "Direct children of Electronics:\n";

foreach ($directChildren as $child) {
    echo "- {$child->name}\n";
}

// Get only direct parent
$smartphone = Category::findOne(['name' => 'Smartphones']);
$directParent = $smartphone->parents(1)->one();

echo "Direct parent of {$smartphone->name}: {$directParent->name}\n";
```

### Finding siblings

```php
<?php

declare(strict_types=1);

// Get the next sibling
$phones = Category::findOne(['name' => 'Mobile Phones']);
$nextSibling = $phones->next()->one();

if ($nextSibling) {
    echo "Next sibling: {$nextSibling->name}\n";
}

// Get previous sibling
$prevSibling = $phones->prev()->one();

if ($prevSibling) {
    echo "Previous sibling: {$prevSibling->name}\n";
}

// Get all siblings (including self)
$allSiblings = $phones->parents(1)->one()->children(1)->all();

echo "All siblings:\n";

foreach ($allSiblings as $sibling) {
    $current = ($sibling->id === $phones->id) ? ' (current)' : '';
    echo "- {$sibling->name}{$current}\n";
}
```

### Finding leaf nodes

```php
<?php

declare(strict_types=1);

// Get all leaf nodes in a subtree
$electronics = Category::findOne(['name' => 'Electronics']);
$leaves = $electronics->leaves()->all();

echo "Leaf categories in Electronics:\n";

foreach ($leaves as $leaf) {
    echo "- {$leaf->name}\n";
}

// Get all leaf nodes in the entire tree
$allLeaves = Category::find()->leaves()->all();

echo "All leaf categories:\n";

foreach ($allLeaves as $leaf) {
    echo "- {$leaf->name}\n";
}
```

### Finding root nodes

```php
<?php

declare(strict_types=1);

// Get all root nodes (useful for multiple trees)
$roots = Category::find()->roots()->all();

echo "All root categories:\n";

foreach ($roots as $root) {
    echo "- {$root->name} (Tree ID: {$root->tree})\n";
}
```

## Moving and reorganizing nodes

### Moving nodes within the same tree

```php
<?php

declare(strict_types=1);

// Move a node to a different parent
$smartphones = Category::findOne(['name' => 'Smartphones']);
$accessories = Category::findOne(['name' => 'Accessories']);

// Move Smartphones to be under Accessories
$smartphones->appendTo($accessories);

// Move as sibling
$tablets = Category::findOne(['name' => 'Tablets']);
$computers = Category::findOne(['name' => 'Computers']);

// Move Tablets to be after Computers
$tablets->insertAfter($computers);
```

### Reorganizing tree structure

```php
<?php

declare(strict_types=1);

class CategoryController extends Controller
{
    public function actionReorganize(): void
    {
        // Reorganize Electronics category structure
        $electronics = Category::findOne(['name' => 'Electronics']);
        
        // Create a new subcategory structure
        $mobile = new Category(['name' => 'Mobile Devices']);
        $mobile->appendTo($electronics);
        
        // Move existing categories under new structure
        $phones = Category::findOne(['name' => 'Mobile Phones']);
        $tablets = Category::findOne(['name' => 'Tablets']);
        
        $phones->appendTo($mobile);
        $tablets->appendTo($mobile);
        
        // Result: Electronics > Mobile Devices > (Mobile Phones, Tablets)
    }
}
```

### Moving nodes between trees (multiple trees only)

```php
<?php

declare(strict_types=1);

// Move a node from one tree to another
$gaming = Category::findOne(['name' => 'Gaming']);
$entertainmentRoot = Category::findOne(['name' => 'Entertainment']);

// Move Gaming from Electronics tree to Entertainment tree
$gaming->appendTo($entertainmentRoot);

// The entire Gaming subtree moves to the new tree
echo "Gaming moved to tree: {$gaming->tree}\n";
```

### Making existing nodes into new roots

```php
<?php

declare(strict_types=1);

// Convert a node and its subtree into a new independent tree
$gaming = Category::findOne(['name' => 'Gaming']);

// Make Gaming a root of its own tree (multiple trees only)
$gaming->makeRoot();

echo "Gaming is now root of tree: {$gaming->tree}\n";

// All descendants of Gaming maintain their relative positions
$gamingChildren = $gaming->children()->all();

foreach ($gamingChildren as $child) {
    echo "- {$child->name} (Tree: {$child->tree})\n";
}
```

## Deleting nodes

### Deleting individual nodes

```php
<?php

declare(strict_types=1);

// Delete a node - children move up to parent
$mobilePhones = Category::findOne(['name' => 'Mobile Phones']);
$children = $mobilePhones->children()->all();

echo "Before deletion, Mobile Phones has " . count($children) . " children\n";

// Delete the node - children become children of Electronics
$mobilePhones->delete();

// Verify children moved up
$electronics = Category::findOne(['name' => 'Electronics']);
$newChildren = $electronics->children(1)->all();

echo "After deletion, Electronics direct children:\n";

foreach ($newChildren as $child) {
    echo "- {$child->name}\n";
}
```

### Deleting nodes with all descendants

```php
<?php

declare(strict_types=1);

// Delete a node and all its descendants
$electronics = Category::findOne(['name' => 'Electronics']);
$descendantsCount = count($electronics->children()->all());

echo "Electronics has {$descendantsCount} descendants\n";

// Delete Electronics and everything under it
$deletedCount = $electronics->deleteWithChildren();

echo "Deleted {$deletedCount} nodes total\n";
```

### Batch deletion with validation

```php
<?php

declare(strict_types=1);

class CategoryService
{
    public function deleteCategory(int $categoryId, bool $deleteChildren = false): array
    {
        $category = Category::findOne($categoryId);
        
        if (!$category) {
            return ['success' => false, 'error' => 'Category not found'];
        }
        
        // Check if category has children
        $hasChildren = $category->children()->exists();
        
        if ($hasChildren && !$deleteChildren) {
            return [
                'success' => false, 
                'error' => 'Category has children. Specify deleteChildren=true to proceed'
            ];
        }
        
        try {
            if ($deleteChildren) {
                $deletedCount = $category->deleteWithChildren();

                return [
                    'success' => true, 
                    'message' => "Deleted category and {$deletedCount} descendants"
                ];
            } else {
                $category->delete();

                return ['success' => true, 'message' => 'Category deleted'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

## Advanced queries and operations

### Finding nodes by depth level

```php
<?php

declare(strict_types=1);

// Find all categories at a specific depth level
$electronics = Category::findOne(['name' => 'Electronics']);

// Get all grandchildren (depth = electronics.depth + 2)
$grandchildren = $electronics->children(2)
    ->andWhere(['depth' => $electronics->depth + 2])
    ->all();

echo "Grandchildren of Electronics:\n";

foreach ($grandchildren as $grandchild) {
    echo "- {$grandchild->name}\n";
}
```

### Building tree menus

```php
<?php

declare(strict_types=1);

class MenuWidget extends Widget
{
    public $rootId;
    public $maxDepth = 3;
    
    public function run(): string
    {
        $root = Category::findOne($this->rootId);
        
        if (!$root) {
            return '';
        }
        
        $tree = $this->buildTreeArray($root);

        return $this->renderTree($tree);
    }
    
    private function buildTreeArray($root): array
    {
        $children = $root->children($this->maxDepth)->all();
        
        $tree = [];
        $currentDepth = $root->depth;
        
        foreach ($children as $node) {
            $tree[] = [
                'id' => $node->id,
                'name' => $node->name,
                'depth' => $node->depth - $currentDepth,
                'url' => Url::to(['category/view', 'id' => $node->id]),
            ];
        }
        
        return $tree;
    }
    
    private function renderTree(array $tree): string
    {
        $html = '<ul class="tree-menu">';
        
        foreach ($tree as $item) {
            $indent = str_repeat('  ', $item['depth']);
            $html .= '<li class="depth-' . $item['depth'] . '">';
            $html .= Html::a($item['name'], $item['url']);
            $html .= '</li>';
        }
        
        $html .= '</ul>';

        return $html;
    }
}
```

### Checking node relationships

```php
<?php

declare(strict_types=1);

class CategoryService 
{
    public function isAncestor(Category $ancestor, Category $descendant): bool
    {
        return $descendant->isChildOf($ancestor);
    }
    
    public function isDescendant(Category $descendant, Category $ancestor): bool
    {
        return $descendant->isChildOf($ancestor);
    }
    
    public function isSibling(Category $node1, Category $node2): bool
    {
        // Nodes are siblings if they have the same direct parent
        $parent1 = $node1->parents(1)->one();
        $parent2 = $node2->parents(1)->one();
        
        return $parent1 && $parent2 && $parent1->id === $parent2->id;
    }
    
    public function getCommonAncestor(Category $node1, Category $node2): ?Category
    {
        $ancestors1 = $node1->parents()->all();
        $ancestors2 = $node2->parents()->all();
        
        // Find common ancestors
        $commonAncestors = array_intersect(
            array_column($ancestors1, 'id'),
            array_column($ancestors2, 'id')
        );
        
        if (empty($commonAncestors)) {
            return null;
        }
        
        // Return the deepest common ancestor
        return Category::findOne(max($commonAncestors));
    }
}
```

### Tree validation and integrity

```php
<?php

declare(strict_types=1);

class TreeValidator
{
    public function validateTreeIntegrity(int $treeId = null): array
    {
        $errors = [];
        
        $query = Category::find();
        if ($treeId !== null) {
            $query->andWhere(['tree' => $treeId]);
        }
        
        $categories = $query->all();
        
        foreach ($categories as $category) {
            // Check left < right
            if ($category->lft >= $category->rgt) {
                $errors[] = "Invalid boundaries for {$category->name}: lft={$category->lft}, rgt={$category->rgt}";
            }
            
            // Check children boundaries
            $children = $category->children(1)->all();
            foreach ($children as $child) {
                if ($child->lft <= $category->lft || $child->rgt >= $category->rgt) {
                    $errors[] = "Child {$child->name} boundaries invalid relative to parent {$category->name}";
                }
                
                if ($child->depth !== $category->depth + 1) {
                    $errors[] = "Child {$child->name} has incorrect depth";
                }
            }
        }
        
        return $errors;
    }
}
```

This comprehensive examples guide demonstrates practical usage patterns for the Yii Nested Sets Behavior across different
scenarios, from basic tree building to complex hierarchical data management in real-world applications.

## Next steps

- 📚 [Installation Guide](installation.md)
- ⚙️ [Configuration Guide](configuration.md)
- 🧪 [Testing Guide](testing.md)
