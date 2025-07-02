<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use LogicException;
use RuntimeException;
use yii\base\{Behavior, NotSupportedException};
use yii\db\{ActiveQuery, ActiveRecord, Connection, Exception, Expression};

use function sprintf;

/**
 * Nested set behavior for managing hierarchical data in {@see ActiveRecord} models.
 *
 * Provides a set of methods and properties to implement the nested sets pattern in Yii {@see ActiveRecord} models,
 * enabling efficient management of hierarchical data structures such as trees and categories.
 *
 * This behavior allows nodes to be inserted, moved, or deleted within the tree, and supports querying for parents,
 * children, leaves, and siblings.
 *
 * The behavior manages the left, right, and depth attributes of each node, and can optionally support multiple trees
 * using a tree attribute.
 *
 * It integrates with a Yii event system and can be attached to any {@see ActiveRecord} model.
 *
 * Key features.
 * - Compatible with Yii {@see ActiveRecord} and event system.
 * - Delete nodes with or without their children.
 * - Insert nodes as root, before/after, or as children of other nodes.
 * - Move nodes within the tree while maintaining integrity.
 * - Query for parents, children, leaves, previous and next siblings.
 * - Supports custom attribute names for left, right, depth, and tree columns.
 *
 * @phpstan-template T of ActiveRecord
 *
 * @phpstan-extends Behavior<T>
 *
 * @property int $depth
 * @property int $lft
 * @property int $rgt
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
class NestedSetsBehavior extends Behavior
{
    /**
     * Operation constant for appending the current node as the last child of a target node.
     */
    public const OPERATION_APPEND_TO = 'appendTo';

    /**
     * Operation constant for deleting a node along with all its descendant nodes.
     */
    public const OPERATION_DELETE_WITH_CHILDREN = 'deleteWithChildren';

    /**
     * Operation constant for inserting the current node immediately after a target node at the same level.
     */
    public const OPERATION_INSERT_AFTER = 'insertAfter';

    /**
     * Operation constant for inserting the current node immediately before a target node at the same level.
     */
    public const OPERATION_INSERT_BEFORE = 'insertBefore';

    /**
     * Operation constant for making the current node a root node in the tree.
     */
    public const OPERATION_MAKE_ROOT = 'makeRoot';

    /**
     * Operation constant for prepending the current node as the first child of a target node.
     */
    public const OPERATION_PREPEND_TO = 'prependTo';

    /**
     * Holds the reference to the current node involved in a nested set operation.
     *
     * Stores the {@see ActiveRecord} instance representing the node being manipulated by the behavior during operations
     * such as insertion, movement, or deletion within the tree structure.
     *
     * @template T of NestedSetsBehavior
     *
     * @phpstan-var ActiveRecord<T>|null
     */
    protected ActiveRecord|null $node = null;

    /**
     * Stores the current operation being performed on the node.
     *
     * Holds the operation type as a string identifier, such as 'appendTo', 'deleteWithChildren', or other defined
     * operation constants.
     */
    protected string|null $operation = null;

    /**
     * Name of the attribute that stores the depth (level) of the node in the tree.
     *
     * @phpstan-var 'depth' attribute name.
     */
    public string $depthAttribute = 'depth';

    /**
     * Name of the attribute that stores the left boundary value of the node in the nested set tree.
     *
     * @phpstan-var 'lft' attribute name.
     */
    public string $leftAttribute = 'lft';

    /**
     * Name of the attribute that stores the right boundary value of the node in the nested set tree.
     *
     * @phpstan-var 'rgt' attribute name.
     */
    public string $rightAttribute = 'rgt';

    /**
     * Name of the attribute that stores the tree identifier for supporting multiple trees.
     */
    public string|false $treeAttribute = false;

    /**
     * Database connection instance used for executing queries.
     *
     * This property is used to access the database connection associated with the attached {@see ActiveRecord} model,
     * allowing the behavior to perform database operations such as updates and queries.
     */
    private Connection|null $db = null;

    /**
     * Handles post-deletion updates for the nested set structure.
     *
     * Updates left, right, and depth attributes of affected nodes after a node is deleted, ensuring the tree remains
     * consistent.
     * - If the node is deleted with its children or is a leaf, shifts the left/right values accordingly.
     * - Otherwise, updates the subtree and shifts attributes for remaining nodes.
     *
     * This method is automatically triggered after a node deletion event and is essential for maintaining the integrity
     * of the nested set hierarchy.
     *
     * Usage example:
     * ```php
     * // `afterDelete()` is called automatically to update the tree structure
     * $model->delete();
     * ``
     */
    public function afterDelete(): void
    {
        $leftValue = $this->getOwner()->getAttribute($this->leftAttribute);
        $rightValue = $this->getOwner()->getAttribute($this->rightAttribute);

        if ($this->operation === self::OPERATION_DELETE_WITH_CHILDREN || $this->getOwner()->isLeaf()) {
            $this->shiftLeftRightAttribute($rightValue, $leftValue - $rightValue - 1);
        } else {
            $condition = [
                'and',
                [
                    '>=',
                    $this->leftAttribute,
                    $this->getOwner()->getAttribute($this->leftAttribute),
                ],
                [
                    '<=',
                    $this->rightAttribute,
                    $this->getOwner()->getAttribute($this->rightAttribute),
                ],
            ];

            $this->applyTreeAttributeCondition($condition);
            $db = $this->getOwner()::getDb();
            $this->getOwner()::updateAll(
                [
                    $this->leftAttribute => new Expression(
                        $db->quoteColumnName($this->leftAttribute) . sprintf('%+d', -1),
                    ),
                    $this->rightAttribute => new Expression(
                        $db->quoteColumnName($this->rightAttribute) . sprintf('%+d', -1),
                    ),
                    $this->depthAttribute => new Expression(
                        $db->quoteColumnName($this->depthAttribute) . sprintf('%+d', -1),
                    ),
                ],
                $condition,
            );
            $this->shiftLeftRightAttribute($rightValue, -2);
        }

        $this->operation = null;
        $this->node = null;
    }

    /**
     * Handles post-insert updates for the nested set structure when making a node root.
     *
     * If the current operation is {@see self::OPERATION_MAKE_ROOT} and the {@see treeAttribute} is enabled, this method
     * sets the tree attribute of the node to its primary key and updates the corresponding record in the database.
     *
     * This ensures that the root node of a tree is correctly identified and its tree attribute is synchronized after
     * insertion.
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * Usage example:
     * ```php
     * // `afterInsert()` is called automatically to set the tree attribute
     * $model->makeRoot();
     * ```
     */
    public function afterInsert(): void
    {
        if ($this->operation === self::OPERATION_MAKE_ROOT && $this->treeAttribute !== false) {
            $this->getOwner()->setAttribute($this->treeAttribute, $this->getOwner()->getPrimaryKey());
            $primaryKey = $this->getOwner()::primaryKey();

            if (isset($primaryKey[0]) === false) {
                throw new Exception('"' . get_class($this->getOwner()) . '" must have a primary key.');
            }

            $this->getOwner()::updateAll(
                [
                    $this->treeAttribute => $this->getOwner()->getAttribute($this->treeAttribute),
                ],
                [
                    $primaryKey[0] => $this->getOwner()->getAttribute($this->treeAttribute),
                ],
            );
        }

        $this->operation = null;
        $this->node = null;
    }

    /**
     * Handles post-update operations for the nested set structure after a node modification.
     *
     * Executes the appropriate node movement logic based on the current operation type, ensuring the integrity and
     * consistency of the nested set hierarchy after an update.
     *
     * This method is automatically triggered after an update event on the attached {@see ActiveRecord} model.
     *
     * The operation performed depends on the value of {@see operation}.
     * - {@see self::OPERATION_APPEND_TO}: Moves the node as the last child of the target node.
     * - {@see self::OPERATION_INSERT_AFTER}: Moves the node as the next sibling of the target node.
     * - {@see self::OPERATION_INSERT_BEFORE}: Moves the node as the previous sibling of the target node.
     * - {@see self::OPERATION_MAKE_ROOT}: Moves the node to become a root node.
     * - {@see self::OPERATION_PREPEND_TO}: Moves the node as the first child of the target node.
     *
     * After the operation, the internal state is reset to prepare for subsequent operations.
     *
     * Usage example:
     * ```php
     * // `afterUpdate()` is called automatically to move the node
     * $model->update();
     * ```
     */
    public function afterUpdate(): void
    {
        $currentOwnerTreeValue = $this->getTreeValue($this->getOwner());

        if ($this->operation === self::OPERATION_MAKE_ROOT) {
            $this->moveNodeAsRoot($currentOwnerTreeValue);
            $this->resetOperationState();
            return;
        }

        if ($this->node === null) {
            $this->resetOperationState();
            return;
        }

        $context = $this->createMoveContext($this->node, $this->operation);

        $this->moveNode($context);
        $this->resetOperationState();
    }

    /**
     * Appends the current node as the last child of the specified target node.
     *
     * - If the attached {@see ActiveRecord} is new, this method creates it as the last child of the given node.
     * - If the record already exists, it moves the node as the last child of the target node, updating the nested set
     *   structure accordingly.
     *
     * This operation is essential for building and maintaining hierarchical data structures, such as categories or menu
     * trees, where nodes can be dynamically inserted or reorganized within the tree.
     *
     * @param ActiveRecord $node Target node to which the current node will be appended as the last child.
     * @param bool $runValidation Whether to perform validation before saving the record.
     * @param array|null $attributes List of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes.
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * @return bool Whether the operation was successful and the node was appended or moved.
     *
     * Usage example:
     * ```php
     * $category->appendTo($parentCategory);
     * ```
     *
     * @phpstan-param T $node
     * @phpstan-param array<string, mixed>|null $attributes
     */
    public function appendTo(ActiveRecord $node, bool $runValidation = true, array|null $attributes = null): bool
    {
        $this->operation = self::OPERATION_APPEND_TO;
        $this->node = $node;

        $result = $this->getOwner()->save($runValidation, $attributes);

        if ($result === true) {
            $node->refresh();
        }

        return $result;
    }

    /**
     * Handles pre-deletion validation and restrictions for the nested set node.
     *
     * Ensures that only valid nodes can be deleted and enforces restrictions on root node deletion unless the operation
     * is explicitly set to delete with children.
     *
     * This method is automatically triggered before a node deletion event and is essential for maintaining the
     * integrity of the nested set hierarchy.
     *
     * @throws Exception if an unexpected error occurs during execution.
     * @throws NotSupportedException if the operation is not supported for the current node.
     *
     * Usage example:
     * ```php
     * // `beforeDelete()` is called automatically to validate the deletion
     * $model->delete();
     * ```
     */
    public function beforeDelete(): void
    {
        if ($this->getOwner()->getIsNewRecord()) {
            throw new Exception('Can not delete a node when it is new record.');
        }

        if ($this->operation !== self::OPERATION_DELETE_WITH_CHILDREN && $this->getOwner()->isRoot()) {
            throw new NotSupportedException(
                'Method "' . get_class($this->getOwner()) . '::delete" is not supported for deleting root nodes.',
            );
        }
    }

    /**
     * Handles pre-insert operations for the nested set structure before a node is inserted.
     *
     * Determines the appropriate insertion logic based on the current operation type and the state of the target node.
     *
     * This method is automatically triggered before an insert event on the attached {@see ActiveRecord} model.
     *
     * It ensures that the nested set attributes are correctly prepared for the intended operation, such as making a
     * node root, prepending/appending as a child, or inserting as a sibling.
     *
     * The operation performed depends on the value of {@see $operation}.
     * - {@see self::OPERATION_APPEND_TO}: Prepares the node to be inserted as the last child of the target node.
     * - {@see self::OPERATION_INSERT_AFTER}: Prepares the node to be inserted as the next sibling of the target node.
     * - {@see self::OPERATION_INSERT_BEFORE}: Prepares the node to be inserted as the previous sibling of the target
     *   node.
     * - {@see self::OPERATION_MAKE_ROOT}: Prepares the node to be inserted as a root node.
     * - {@see self::OPERATION_PREPEND_TO}: Prepares the node to be inserted as the first child of the target node.
     *
     * If the target node is not new, it is refreshed to ensure up-to-date attribute values before insertion.
     *
     * @throws Exception if an unexpected error occurs during execution.
     * @throws NotSupportedException if the operation is not supported for the current node.
     *
     * Usage example:
     * ```php
     * // `beforeInsert()` is called automatically to prepare the node for insertion
     * $model->insert();
     * ```
     */
    public function beforeInsert(): void
    {
        match (true) {
            $this->operation === self::OPERATION_APPEND_TO && $this->node !== null => $this->beforeInsertNode(
                $this->node->getAttribute($this->rightAttribute),
                1,
            ),
            $this->operation === self::OPERATION_INSERT_AFTER && $this->node !== null => $this->beforeInsertNode(
                $this->node->getAttribute($this->rightAttribute) + 1,
                0,
            ),
            $this->operation === self::OPERATION_INSERT_BEFORE && $this->node !== null => $this->beforeInsertNode(
                $this->node->getAttribute($this->leftAttribute),
                0,
            ),
            $this->operation === self::OPERATION_MAKE_ROOT => $this->beforeInsertRootNode(),
            $this->operation === self::OPERATION_PREPEND_TO && $this->node !== null => $this->beforeInsertNode(
                $this->node->getAttribute($this->leftAttribute) + 1,
                1,
            ),
            default => throw new NotSupportedException(
                'Method "' . get_class($this->getOwner()) . '::insert" is not supported for inserting new nodes.',
            ),
        };
    }

    /**
     * Handles pre-update validation and restrictions for the nested set node.
     *
     * Ensures that only valid node movements are allowed and enforces restrictions on moving nodes within the tree
     * structure.
     *
     * This method is automatically triggered before an update event on the attached {@see ActiveRecord} model and is
     * essential for maintaining the integrity of the nested set hierarchy during node movement operations.
     *
     * The operation performed depends on the value of {@see $operation}.
     * - {@see self::OPERATION_MAKE_ROOT}: Validates that the node can be moved as root and that the tree attribute is
     *   enabled.
     * - {@see self::OPERATION_INSERT_AFTER}, {@see self::OPERATION_INSERT_BEFORE}: Prevents moving a node when the
     *   target node is root.
     * - {@see self::OPERATION_APPEND_TO}, {@see self::OPERATION_PREPEND_TO}: Prevents moving a node to an invalid
     *   target (new record, same node, or child node).
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * Usage example:
     * ```php
     * // `beforeUpdate()` is called automatically to validate the movement
     * $model->update();
     * ```
     */
    public function beforeUpdate(): void
    {
        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                if ($this->treeAttribute === false) {
                    throw new Exception('Can not move a node as the root when "treeAttribute" is false.');
                }

                if ($this->getOwner()->isRoot()) {
                    throw new Exception('Can not move the root node as the root.');
                }

                break;
            case self::OPERATION_INSERT_AFTER:
            case self::OPERATION_INSERT_BEFORE:
                if ($this->node?->isRoot() === true) {
                    throw new Exception('Can not move a node when the target node is root.');
                }
                // no break
            case self::OPERATION_APPEND_TO:
            case self::OPERATION_PREPEND_TO:
                if ($this->node?->getIsNewRecord() === true) {
                    throw new Exception('Can not move a node when the target node is new record.');
                }

                if ($this->node !== null && $this->getOwner()->equals($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }

                if ($this->node !== null && $this->node->isChildOf($this->getOwner())) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
        }
    }

    /**
     * Returns an {@see ActiveQuery} for the children of the current node, optionally limited by depth.
     *
     * Retrieves all descendant nodes that are children of the current node, ordered by the left attribute ascending.
     *
     * If the optional `$depth` parameter is provided, only children up to the specified depth relative to the current
     * node are included.
     *
     * The query automatically applies the tree attribute condition if configured, ensuring correct results for
     * multi-tree structures.
     *
     * @param int|null $depth Maximum depth relative to the current node, or `null` for all descendants.
     *
     * @return ActiveQuery ActiveQuery instance for fetching child nodes.
     *
     * Usage example:
     * ```php
     * // Retrieves all children of the current node
     * $children = $model->children();
     * ```
     *
     * @phpstan-return ActiveQuery<T>
     */
    public function children(int|null $depth = null): ActiveQuery
    {
        $condition = [
            'and',
            [
                '>',
                $this->leftAttribute, $this->getOwner()->getAttribute($this->leftAttribute),
            ],
            [
                '<',
                $this->rightAttribute, $this->getOwner()->getAttribute($this->rightAttribute),
            ],
        ];

        if ($depth !== null) {
            $condition[] = [
                '<=',
                $this->depthAttribute,
                $this->getOwner()->getAttribute($this->depthAttribute) + $depth,
            ];
        }

        $this->applyTreeAttributeCondition($condition);

        return $this->getOwner()::find()->andWhere($condition)->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Deletes the current node and all its descendant nodes from the nested set tree.
     *
     * Removes the node to which this behavior is attached, along with all its children, from the hierarchical
     * structure.
     *
     * This method sets the internal operation state to {@see self::OPERATION_DELETE_WITH_CHILDREN} and performs the
     * deletion within a database transaction if the model is not already transactional for delete operations.
     *
     * The deletion is delegated to {@see deleteWithChildrenInternal()}, which executes the actual removal logic.
     *
     * If the deletion fails, the transaction is rolled back to maintain data integrity.
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * @return bool|int Number of rows deleted, or false if the deletion is unsuccessful.
     *
     * Usage example:
     * ```php
     * $model->deleteWithChildren();
     * ```
     */
    public function deleteWithChildren(): bool|int
    {
        $this->operation = self::OPERATION_DELETE_WITH_CHILDREN;

        if ($this->getOwner()->isTransactional(ActiveRecord::OP_DELETE) !== false) {
            return $this->deleteWithChildrenInternal();
        }

        $transaction = $this->getOwner()::getDb()->beginTransaction();

        try {
            match ($result = $this->deleteWithChildrenInternal()) {
                false => $transaction->rollBack(),
                default => $transaction->commit(),
            };

            return $result;
        } catch (Exception $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Declares event handlers for {@see ActiveRecord} lifecycle events.
     *
     * This method enables the behavior to automatically respond to insert, update, and delete operations on the
     * attached {@see ActiveRecord} model by invoking the appropriate handler methods.
     *
     * It ensures that the nested set structure remains consistent during model persistence operations.
     *
     * @return array Event-to-handler map for {@see ActiveRecord} lifecycle events.
     *
     * Usage example:
     * ```php
     * // Behavior will automatically handle events like `beforeInsert`, `afterInsert`, etc.
     * $model->attachBehavior('nestedSets', new NestedSetsBehavior());
     * ```
     *
     * @phpstan-return string[]
     */
    public function events(): array
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeInsert',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * Inserts the current node as the next sibling of the specified target node or moves it if it already exists.
     *
     * - If the attached {@see ActiveRecord} is a new record, this method creates it as the next sibling of the given
     *   node.
     * - If the record already exists, it moves the node as the next sibling of the target node, updating the nested set
     *   structure accordingly.
     *
     * This operation is essential for maintaining hierarchical data structures where nodes can be dynamically inserted
     * or reorganized within the tree at the same level as the target node.
     *
     * The method sets the internal operation state to {@see self::OPERATION_INSERT_AFTER} and stores the reference to
     * the target node.
     *
     * The actual insertion or movement is performed by saving the owner model, which triggers the appropriate lifecycle
     * events and updates the tree structure.
     *
     * @param ActiveRecord $node Target node after which the current node will be inserted as the next sibling.
     * @param bool $runValidation Whether to perform validation before saving the record.
     * @param array|null $attributes List of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes.
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * @return bool Whether the operation was successful and the node was inserted or moved.
     *
     * Usage example:
     * ```php
     * $category->insertAfter($siblingCategory);
     * ```
     *
     * @phpstan-param array<string, mixed>|null $attributes
     */
    public function insertAfter(ActiveRecord $node, bool $runValidation = true, array|null $attributes = null): bool
    {
        $this->operation = self::OPERATION_INSERT_AFTER;
        $this->node = $node;

        return $this->getOwner()->save($runValidation, $attributes);
    }

    /**
     * Inserts the current node as the previous sibling of the specified target node or moves it if it already exists.
     *
     * - If the attached {@see ActiveRecord} is a new record, this method creates it as the previous sibling of the
     *   given node.
     * - If the record already exists, it moves the node as the previous sibling of the target node, updating the nested
     *   set structure accordingly.
     *
     * This operation is essential for maintaining hierarchical data structures where nodes can be dynamically inserted
     * or reorganized within the tree at the same level as the target node.
     *
     * The method sets the internal operation state to {@see self::OPERATION_INSERT_BEFORE} and stores the reference to
     * the target node.
     *
     * The actual insertion or movement is performed by saving the owner model, which triggers the appropriate lifecycle
     * events and updates the tree structure.
     *
     * @param ActiveRecord $node Target node before which the current node will be inserted as the previous sibling.
     * @param bool $runValidation Whether to perform validation before saving the record.
     * @param array|null $attributes List of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes.
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * @return bool Whether the operation was successful and the node was inserted or moved.
     *
     * Usage example:
     * ```php
     * $category->insertBefore($siblingCategory);
     * ```
     *
     * @phpstan-param array<string, mixed>|null $attributes
     */
    public function insertBefore(ActiveRecord $node, bool $runValidation = true, array|null $attributes = null): bool
    {
        $this->operation = self::OPERATION_INSERT_BEFORE;
        $this->node = $node;

        return $this->getOwner()->save($runValidation, $attributes);
    }

    /**
     * Determines whether the current node is a direct or indirect child of the specified parent node.
     *
     * Evaluates the nested set boundaries to check if the node to which this behavior is attached is contained within
     * the left and right boundaries of the given parent node.
     *
     * If the behavior is configured for multi-tree support (that is, {@see treeAttribute} is enabled), the method also
     * verifies that both nodes belong to the same tree by comparing their tree attribute values.
     *
     * This method is useful for validating hierarchical relationships, enforcing tree integrity, and implementing
     * access or movement restrictions based on parent-child relationships within the nested set structure.
     *
     * @param ActiveRecord $node Parent node to check against.
     *
     * @return bool Whether the current node is a child (descendant) of the specified parent node.
     *
     * Usage example:
     * ```php
     * if ($category->isChildOf($parentCategory)) {
     *     // Perform logic for child nodes
     * }
     * ```
     */
    public function isChildOf(ActiveRecord $node): bool
    {
        $owner = $this->getOwner();

        $currentLeft = $owner->getAttribute($this->leftAttribute);
        $currentRight = $owner->getAttribute($this->rightAttribute);
        $nodeLeft = $node->getAttribute($this->leftAttribute);
        $nodeRight = $node->getAttribute($this->rightAttribute);

        if ($currentLeft <= $nodeLeft || $currentRight >= $nodeRight) {
            return false;
        }

        if ($this->treeAttribute !== false) {
            return $owner->getAttribute($this->treeAttribute) === $node->getAttribute($this->treeAttribute);
        }

        return true;
    }

    /**
     * Determines whether the current node is a leaf node in the nested set tree.
     *
     * Evaluates the left and right attribute values of the node to check if it has no children, according to the nested
     * sets model.
     *
     * A node is considered a leaf if the difference between its right and left attribute values is exactly one,
     * indicating that it has no child nodes within the tree structure.
     *
     * This method is useful for identifying terminal nodes in hierarchical data, enabling logic for deletion, movement,
     * or display of leaf nodes in tree-based structures.
     *
     * @return bool Whether the current node is a leaf node (has no children).
     *
     * Usage example:
     * ```php
     * if ($category->isLeaf()) {
     *     // Perform logic for leaf nodes
     * }
     * ```
     */
    public function isLeaf(): bool
    {
        return $this->getOwner()
            ->getAttribute($this->rightAttribute) - $this->getOwner()->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * Determines whether the current node is a root node in the nested set tree.
     *
     * Evaluates the left attribute value of the node to check if it is positioned as the root node within the tree
     * structure.
     *
     * A node is considered root if its left attribute value is exactly one, which is the standard convention in the
     * nested sets model for identifying the root node of a tree.
     *
     * This method is useful for validating root status, enforcing tree integrity, and implementing logic that applies
     * only to root nodes in hierarchical data structures.
     *
     * @return bool Whether the current node is a root node (left attribute equals one).
     *
     * Usage example:
     * ```php
     * if ($category->isRoot()) {
     *     // Perform logic for root nodes
     * }
     * ```
     */
    public function isRoot(): bool
    {
        return $this->getOwner()->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * Retrieves all leaf nodes that are descendants of the current node.
     *
     * Returns an {@see ActiveQuery} representing all leaf nodes (nodes without children) that are contained within the
     * left and right boundaries of the node to which this behavior is attached.
     *
     * A node is considered a leaf if its right attribute value is exactly one greater than its left attribute value.
     *
     * This method constructs a query to select all such nodes that are descendants of the current node, ordered by the
     * left attribute in ascending order.
     *
     * This is useful for efficiently retrieving all terminal nodes in a subtree, such as for rendering category trees,
     * generating navigation menus, or performing operations on leaf nodes only.
     *
     * @return ActiveQuery ActiveQuery instance for all leaf nodes under the current node.
     *
     * Usage example:
     * ```php
     * // Get all leaf nodes under the current node
     * $leaves = $model->leaves()->all();
     * ```
     *
     * @phpstan-return ActiveQuery<T>
     */
    public function leaves(): ActiveQuery
    {
        $condition = [
            'and',
            [
                '>',
                $this->leftAttribute, $this->getOwner()->getAttribute($this->leftAttribute),
            ],
            [
                '<',
                $this->rightAttribute, $this->getOwner()->getAttribute($this->rightAttribute),
            ],
            [
                $this->rightAttribute => new Expression(
                    $this->getOwner()::getDb()->quoteColumnName($this->leftAttribute) . '+ 1',
                ),
            ],
        ];

        $this->applyTreeAttributeCondition($condition);

        return $this->getOwner()::find()->andWhere($condition)->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Creates the root node if the active record is new, or moves it as the root node in the nested set tree.
     *
     * Sets the internal operation state to {@see self::OPERATION_MAKE_ROOT} and triggers the save process on the owner
     * model.
     *
     * - If the attached {@see ActiveRecord} is a new record, this method creates it as the root node of a new tree,
     *   setting `left=1`, `right=2`, and `depth=0`.
     * - If the record already exists, it moves the node to become the root node, updating the nested set structure
     *   accordingly and adjusting all affected nodes in the tree.
     *
     * This operation is essential for initializing or reorganizing hierarchical data structures, such as category
     * trees, where nodes can be promoted to root status or new trees can be started.
     *
     * The actual creation or movement is performed by saving the owner model, which triggers the appropriate lifecycle
     * events and updates the tree structure. Upon successful save, the model is automatically refreshed to ensure all
     * nested set attributes reflect their current database values.
     *
     * @param bool $runValidation Whether to perform validation before saving the record.
     * @param array|null $attributes List of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes.
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * @return bool Whether the operation was successful and the node was created or moved as root.
     *
     * Usage example:
     * ```php
     * // Create a new root node
     * $category = new Category(['name' => 'Electronics']);
     * $category->makeRoot();
     *
     * // Move existing node to become root
     * $existingNode = Category::findOne(5);
     * $existingNode->makeRoot();
     * ```
     *
     * @phpstan-param array<string, mixed>|null $attributes
     */
    public function makeRoot(bool $runValidation = true, array|null $attributes = null): bool
    {
        $this->operation = self::OPERATION_MAKE_ROOT;

        $owner = $this->getOwner();

        $result = $owner->save($runValidation, $attributes);

        if ($result === true) {
            $owner->refresh();
        }

        return $result;
    }

    /**
     * Returns an {@see ActiveQuery} for the next sibling node in the nested set tree.
     *
     * Constructs a query to retrieve the node whose left attribute value is exactly one greater than the right
     * attribute value of the current node, selecting the immediate next sibling at the same hierarchical level.
     *
     * If the behavior is configured for multi-tree support (that is, {@see treeAttribute} is enabled), the query will
     * include a condition to ensure that the sibling belongs to the same tree as the current node.
     *
     * This method is useful for traversing sibling nodes in hierarchical data structures, such as categories or menu
     * trees, and enables efficient navigation or manipulation of adjacent nodes within the same parent.
     *
     * @return ActiveQuery ActiveQuery instance for the next sibling node, or an empty result if there is no next
     * sibling.
     *
     * Usage example:
     * ```php
     * // Get the next sibling of the current node
     * $nextSibling = $model->next()->one();
     * ```
     *
     * @phpstan-return ActiveQuery<T>
     */
    public function next(): ActiveQuery
    {
        $condition = [$this->leftAttribute => $this->getOwner()->getAttribute($this->rightAttribute) + 1];
        $this->applyTreeAttributeCondition($condition);

        return $this->getOwner()::find()->andWhere($condition);
    }

    /**
     * Retrieves all ancestor nodes (parents) of the current node in the nested set tree.
     *
     * Returns an {@see ActiveQuery} representing all parent nodes of the node to which this behavior is attached,
     * ordered by the left attribute in ascending order.
     *
     * The query includes all nodes that enclose the current node according to the nested sets model, optionally limited
     * by a specified depth.
     *
     * This method is useful for traversing the hierarchy upwards, such as for building breadcrumb navigation,
     * validating ancestry, or applying logic based on parent relationships in hierarchical data structures.
     *
     * If the optional `$depth` parameter is provided, only parents within the specified depth from the current node
     * will be included in the result.
     *
     * @param int|null $depth Optional depth limit for parent retrieval.
     * - If set, only parents within this depth from the current node are returned.
     * - If `null`, all ancestor nodes are returned.
     *
     * @return ActiveQuery ActiveQuery instance for all parent nodes of the current node, ordered by left attribute.
     *
     * Usage example:
     * ```php
     * // Get all parent nodes (ancestors) of the current node
     * $parents = $model->parents()->all();
     *
     * // Get parents up to 2 levels above the current node
     * $limitedParents = $model->parents(2)->all();
     * ```
     *
     * @phpstan-return ActiveQuery<T>
     */
    public function parents(int|null $depth = null): ActiveQuery
    {
        $condition = [
            'and',
            [
                '<',
                $this->leftAttribute, $this->getOwner()->getAttribute($this->leftAttribute),
            ],
            [
                '>',
                $this->rightAttribute, $this->getOwner()->getAttribute($this->rightAttribute),
            ],
        ];

        if ($depth !== null) {
            $condition[] = [
                '>=',
                $this->depthAttribute, $this->getOwner()->getAttribute($this->depthAttribute) - $depth,
            ];
        }

        $this->applyTreeAttributeCondition($condition);

        return $this->getOwner()::find()->andWhere($condition)->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Inserts the current node as the first child of the specified target node or moves it if it already exists.
     *
     * - If the attached {@see ActiveRecord} is a new record, this method creates it as the first child of the given
     *   node.
     * - If the record already exists, it moves the node as the first child of the target node, updating the nested set
     *   structure accordingly.
     *
     * This operation is essential for building and maintaining hierarchical data structures, such as categories or menu
     * trees, where nodes can be dynamically inserted or reorganized within the tree.
     *
     * The method sets the internal operation state to {@see self::OPERATION_PREPEND_TO} and stores the reference to the
     * target node.
     *
     * The actual insertion or movement is performed by saving the owner model, which triggers the appropriate lifecycle
     * events and updates the tree structure.
     *
     * @param ActiveRecord $node Target node to which the current node will be prepended as the first child.
     * @param bool $runValidation Whether to perform validation before saving the record.
     * @param array|null $attributes List of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes.
     *
     * @throws Exception if an unexpected error occurs during execution.
     *
     * @return bool Whether the operation was successful and the node was prepended or moved.
     *
     * Usage example:
     * ```php
     * $category->prependTo($parentCategory);
     * ```
     *
     * @phpstan-param array<string, mixed>|null $attributes
     */
    public function prependTo(ActiveRecord $node, bool $runValidation = true, array|null $attributes = null): bool
    {
        $this->operation = self::OPERATION_PREPEND_TO;
        $this->node = $node;

        return $this->getOwner()->save($runValidation, $attributes);
    }

    /**
     * Returns an {@see ActiveQuery} for the previous sibling node in the nested set tree.
     *
     * Constructs a query to retrieve the node whose right attribute value is exactly one less than the left attribute
     * value of the current node, selecting the immediate previous sibling at the same hierarchical level.
     *
     * If the behavior is configured for multi-tree support (that is, {@see treeAttribute} is enabled), the query will
     * include a condition to ensure that the sibling belongs to the same tree as the current node.
     *
     * This method is useful for traversing sibling nodes in hierarchical data structures, such as categories or menu
     * trees, and enables efficient navigation or manipulation of adjacent nodes within the same parent.
     *
     * @return ActiveQuery ActiveQuery instance for the previous sibling node, or an empty result if there is no
     * previous sibling.
     *
     * Usage example:
     * ```php
     * // Get the previous sibling of the current node
     * $prevSibling = $model->prev()->one();
     * ```
     *
     * @phpstan-return ActiveQuery<T>
     */
    public function prev(): ActiveQuery
    {
        $condition = [$this->rightAttribute => $this->getOwner()->getAttribute($this->leftAttribute) - 1];
        $this->applyTreeAttributeCondition($condition);

        return $this->getOwner()::find()->andWhere($condition);
    }

    /**
     * Adds the tree attribute condition to the given query condition if multi-tree support is enabled.
     *
     * If the {@see treeAttribute} property is set (not `false`), this method augments the provided condition array with
     * an additional constraint to ensure that queries are limited to the same tree as the current node.
     *
     * This is essential for supporting multiple independent trees within the same table, preventing cross-tree
     * operations and ensuring data integrity when filtering or updating nodes.
     *
     * The method is used internally by query builders such as {@see leaves()}, {@see next()}, {@see parents()},
     * {@see prev()}, and {@see deleteWithChildrenInternal()} to automatically scope queries to the correct tree.
     *
     * @param array $condition Query condition to be modified by reference.
     *
     * @phpstan-param array<int|string, mixed> $condition
     */
    protected function applyTreeAttributeCondition(array &$condition): void
    {
        if ($this->treeAttribute !== false) {
            $condition = [
                'and',
                $condition,
                [
                    $this->treeAttribute => $this->getOwner()->getAttribute($this->treeAttribute),
                ],
            ];
        }
    }

    /**
     * Prepares the current node for insertion as a child or sibling in the nested set tree.
     *
     * Sets the left, right, and depth attributes of the node to be inserted, based on the target node and the specified
     * depth.
     *
     * This method is called internally before inserting a node as a child or sibling, ensuring the nested set
     * attributes are correctly initialized.
     *
     * @param int|null $value Left attribute value for the new node, or `null` if not applicable.
     * @param int $depth Depth offset relative to the target node (`0` for sibling, `1` for child).
     *
     * @throws Exception if an unexpected error occurs during execution.
     */
    protected function beforeInsertNode(int|null $value, int $depth): void
    {
        if ($this->node?->getIsNewRecord() === true) {
            throw new Exception('Can not create a node when the target node is new record.');
        }

        if ($depth === 0 && $this->node?->isRoot() === true) {
            throw new Exception('Can not create a node when the target node is root.');
        }

        if ($value === null) {
            throw new Exception('Value cannot be \'null\' in \'beforeInsertNode()\' method.');
        }

        $this->getOwner()->setAttribute($this->leftAttribute, $value);
        $this->getOwner()->setAttribute($this->rightAttribute, $value + 1);

        $nodeDepthValue = $this->node?->getAttribute($this->depthAttribute) ?? 0;

        $this->getOwner()->setAttribute($this->depthAttribute, $nodeDepthValue + $depth);

        if ($this->treeAttribute !== false && $this->node !== null) {
            $this->getOwner()->setAttribute($this->treeAttribute, $this->node->getAttribute($this->treeAttribute));
        }

        $this->shiftLeftRightAttribute($value, 2);
    }

    /**
     * Prepares the current node for insertion as a root node in the nested set tree.
     *
     * Sets the left, right, and depth attributes of the node to their initial values, establishing it as the root node
     * of the tree.
     *
     * If multi-tree support is disabled (that is, {@see treeAttribute} is `false`), this method ensures that only one
     * root node can exist by checking for the presence of an existing root and throwing an exception if another root is
     * found.
     *
     * This method is called internally before inserting a root node and is essential for maintaining the integrity of
     * the nested set structure.
     *
     * @throws Exception if an unexpected error occurs during execution.
     */
    protected function beforeInsertRootNode(): void
    {
        if ($this->treeAttribute === false && $this->getOwner()::find()->roots()->exists()) {
            throw new Exception('Can not create more than one root when "treeAttribute" is false.');
        }

        $this->getOwner()->setAttribute($this->leftAttribute, 1);
        $this->getOwner()->setAttribute($this->rightAttribute, 2);
        $this->getOwner()->setAttribute($this->depthAttribute, 0);
    }

    /**
     * Deletes the current node and all its descendant nodes from the nested set tree.
     *
     * Executes a bulk deletion of the node to which this behavior is attached, along with all its children, by
     * constructing a condition that matches all nodes within the left and right boundaries of the current node.
     *
     * This method is used internally to efficiently remove entire subtrees in a single operation, ensuring the
     * integrity of the nested set structure.
     *
     * It also triggers the appropriate lifecycle events before and after deletion, and resets the old attributes of the
     * owner model.
     *
     * The method applies the tree attribute condition if multi-tree support is enabled, restricting the deletion to
     * nodes within the same tree.
     *
     * @return false|int Number of rows deleted, or `false` if the deletion is unsuccessful for any reason.
     */
    protected function deleteWithChildrenInternal(): bool|int
    {
        if ($this->getOwner()->beforeDelete() === false) {
            return false;
        }

        $condition = [
            'and',
            [
                '>=',
                $this->leftAttribute, $this->owner?->getAttribute($this->leftAttribute),
            ],
            [
                '<=',
                $this->rightAttribute, $this->owner?->getAttribute($this->rightAttribute),
            ],
        ];

        $this->applyTreeAttributeCondition($condition);
        $result = $this->getOwner()::deleteAll($condition);
        $this->getOwner()->setOldAttributes(null);
        $this->getOwner()->afterDelete();

        return $result;
    }

    /**
     * Executes node movement using the provided context.
     *
     * Handles both same-tree and cross-tree movements, determining the strategy based on tree attribute configuration
     * and tree values from the context.
     *
     * @param NodeContext $context Immutable context containing all movement data.
     */
    protected function moveNode(NodeContext $context): void
    {
        $currentOwnerTreeValue = $this->getTreeValue($this->getOwner());
        $targetNodeTreeValue = $context->getTargetTreeValue($this->treeAttribute);
        $targetNodeDepthValue = $context->getTargetDepth($this->depthAttribute);
        $ownerDepthValue = $this->getOwner()->getAttribute($this->depthAttribute);
        $ownerLeftValue = $this->getOwner()->getAttribute($this->leftAttribute);
        $ownerRightValue = $this->getOwner()->getAttribute($this->rightAttribute);

        $depthOffset = $targetNodeDepthValue - $ownerDepthValue + $context->depthLevelDelta;

        if ($this->treeAttribute === false || $targetNodeTreeValue === $currentOwnerTreeValue) {
            $subtreeSize = $ownerRightValue - $ownerLeftValue + 1;

            $this->shiftLeftRightAttribute($context->targetPositionValue, $subtreeSize);

            if ($ownerLeftValue >= $context->targetPositionValue) {
                $ownerLeftValue += $subtreeSize;
                $ownerRightValue += $subtreeSize;
            }

            $condition = [
                'and',
                [
                    '>=',
                    $this->leftAttribute,
                    $ownerLeftValue,
                ],
                [
                    '<=',
                    $this->rightAttribute,
                    $ownerRightValue,
                ],
            ];

            $this->applyTreeAttributeCondition($condition);
            $this->getOwner()::updateAll(
                [
                    $this->depthAttribute => new Expression(
                        $this->getDb()->quoteColumnName($this->depthAttribute) . sprintf('%+d', $depthOffset),
                    ),
                ],
                $condition,
            );

            foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
                $condition = [
                    'and',
                    [
                        '>=',
                        $attribute, $ownerLeftValue,
                    ],
                    [
                        '<=',
                        $attribute, $ownerRightValue,
                    ],
                ];

                $this->applyTreeAttributeCondition($condition);
                $this->getOwner()::updateAll(
                    [
                        $attribute => new Expression(
                            $this->getDb()->quoteColumnName($attribute) . sprintf('%+d', $context->targetPositionValue - $ownerLeftValue),
                        ),
                    ],
                    $condition,
                );
            }

            $this->shiftLeftRightAttribute($ownerRightValue, -$subtreeSize);
        } else {
            foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
                $this->getOwner()::updateAll(
                    [
                        $attribute => new Expression(
                            $this->getDb()->quoteColumnName($attribute) . sprintf('%+d', $ownerRightValue - $ownerLeftValue + 1),
                        ),
                    ],
                    [
                        'and',
                        [
                            '>=',
                            $attribute,
                            $context->targetPositionValue,
                        ],
                        [
                            $this->treeAttribute => $targetNodeTreeValue,
                        ],
                    ],
                );
            }

            $this->moveSubtreeToTargetTree(
                $targetNodeTreeValue,
                $currentOwnerTreeValue,
                $depthOffset,
                $ownerLeftValue,
                $context->targetPositionValue - $ownerLeftValue,
                $ownerRightValue,
            );
            $this->shiftLeftRightAttribute($ownerRightValue, $ownerLeftValue - $ownerRightValue - 1);
        }
    }

    /**
     * Moves the current node and its descendants to become a new root in the nested set tree.
     *
     * Updates the left, right, and depth attributes of the node and all its descendants so that the node becomes a root
     * node.
     *
     * If multi-tree support is enabled (that is, {@see treeAttribute} is not `false`), the tree attribute is updated to
     * the node primary key.
     *
     * This method is used internally when a node is promoted to root, ensuring the nested set structure remains
     * consistent and all affected nodes are updated in a single operation.
     *
     * The method performs the following operations.
     * - Resets the depth of the node and its descendants to zero-based.
     * - Shifts left and right boundaries of the node and its descendants to start from 1.
     * - Shifts left/right values of remaining nodes to close the gap left by the moved subtree.
     * - Updates the tree attribute to the new root identifier if multi-tree is enabled.
     *
     * @param mixed $treeValue Tree attribute value to which the node will be moved, or `false` if not applicable.
     */
    protected function moveNodeAsRoot(mixed $treeValue): void
    {
        $depthValue = $this->getOwner()->getAttribute($this->depthAttribute);
        $leftValue = $this->getOwner()->getAttribute($this->leftAttribute);
        $nodeRootValue = $this->getOwner()->getPrimaryKey();
        $rightValue = $this->getOwner()->getAttribute($this->rightAttribute);

        $this->moveSubtreeToTargetTree(
            $nodeRootValue,
            $treeValue,
            -$depthValue,
            $leftValue,
            1 - $leftValue,
            $rightValue,
        );
        $this->shiftLeftRightAttribute($rightValue, $leftValue - $rightValue - 1);
    }

    /**
     * Shifts left and right attribute values for nodes after a structural change in the nested set tree.
     *
     * Updates the left and right boundary attributes of all nodes whose attribute value is greater than or equal to the
     * specified value, applying the given delta.
     *
     * This operation is essential for maintaining the integrity of the nested set structure after insertions,
     * deletions, or moves, ensuring that all affected nodes are correctly renumbered.
     *
     * The method applies the tree attribute condition if multi-tree support is enabled, restricting the update to nodes
     * within the same tree.
     *
     * @param int $value Attribute value from which to start shifting (inclusive).
     * @param int $delta Amount to add to the attribute value for affected nodes (can be negative).
     */
    protected function shiftLeftRightAttribute(int $value, int $delta): void
    {
        foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
            $condition = ['>=', $attribute, $value];

            $this->applyTreeAttributeCondition($condition);
            $this->getOwner()::updateAll(
                [
                    $attribute => new Expression($this->getDb()->quoteColumnName($attribute) . sprintf('%+d', $delta)),
                ],
                $condition,
            );
        }
    }

    /**
     * Creates a typed movement context based on operation and target node.
     *
     * @param ActiveRecord $targetNode Target node for the operation.
     * @param string|null $operation Operation type to perform.
     *
     * @throws RuntimeException if a runtime error prevents the operation from completing successfully.
     *
     * @return NodeContext New instance with the specified parameters for the operation.
     */
    private function createMoveContext(ActiveRecord $targetNode, string|null $operation): NodeContext
    {
        return match ($operation) {
            self::OPERATION_APPEND_TO => NodeContext::forAppendTo($targetNode, $this->rightAttribute),
            self::OPERATION_INSERT_AFTER => NodeContext::forInsertAfter($targetNode, $this->rightAttribute),
            self::OPERATION_INSERT_BEFORE => NodeContext::forInsertBefore($targetNode, $this->leftAttribute),
            self::OPERATION_PREPEND_TO => NodeContext::forPrependTo($targetNode, $this->leftAttribute),
            default => throw new RuntimeException("Unsupported operation: {$operation}"),
        };
    }

    /**
     * Retrieves and caches the {@see Connection} object associated with the owner model.
     *
     * The connection is resolved on first access and stored for subsequent calls to improve performance and avoid
     * redundant lookups.
     *
     * This method is used internally by operations that require direct database access, such as bulk updates or
     * structural modifications to the nested set tree.
     *
     * @return Connection Database connection instance for the owner model.
     */
    private function getDb(): Connection
    {
        return $this->db ??= $this->getOwner()::getDb();
    }

    /**
     * Returns the {@see ActiveRecord} instance to which this behavior is currently attached.
     *
     * Ensures that the behavior has a valid owner before performing any operations that require access to the model
     * instance.
     *
     * This method is used internally by all operations that manipulate the nested set structure, providing type safety
     * and a clear error if the behavior is not attached.
     *
     * @throws LogicException if the behavior is not attached to an owner model.
     *
     * @return ActiveRecord Owner model instance to which this behavior is attached.
     *
     * @phpstan-return T
     */
    private function getOwner(): ActiveRecord
    {
        if ($this->owner === null) {
            throw new LogicException('The "owner" property must be set before using the behavior.');
        }

        return $this->owner;
    }

    /**
     * Retrieves the tree attribute value from the specified {@see ActiveRecord} instance.
     *
     * Extracts the tree identifier value from the given model instance when multi-tree support is enabled, providing
     * a centralized method for accessing tree attribute values throughout the behavior.
     *
     * The method is used internally by movement operations, tree validation, and condition building to ensure proper
     * tree scoping and maintain data integrity across different tree contexts.
     *
     * @param ActiveRecord|null $activeRecord Model instance from which to extract the tree value, or `null` if not
     * available.
     *
     * @return mixed Tree attribute value if multi-tree support is enabled and the record exists, `null` otherwise.
     */
    private function getTreeValue(ActiveRecord|null $activeRecord): mixed
    {
        return $activeRecord !== null && $this->treeAttribute !== false
            ? $activeRecord->getAttribute($this->treeAttribute)
            : null;
    }

    /**
     * Moves a subtree to a different tree or position within a multi-tree nested set structure.
     *
     * Updates the left, right, and depth attributes, as well as the tree attribute, for all nodes in the specified
     * subtree.
     *
     * This method is used internally when moving a node and its descendants across trees or to a new root, ensuring
     * that all affected nodes are updated in a single bulk operation.
     *
     * This operation is essential for maintaining the integrity of the nested set structure when reorganizing nodes
     * between trees or promoting a node to root in a multi-tree configuration.
     *
     * @param mixed $targetNodeTreeValue Value to assign to the tree attribute for all nodes in the moved subtree.
     * @param mixed $currentOwnerTreeValue Current tree attribute value of the nodes being moved.
     * @param int $depth Depth offset to apply to all nodes in the subtree.
     * @param int $leftValue Left boundary value of the subtree to move.
     * @param int $positionOffset Amount to shift left and right attribute values for the subtree.
     * @param int $rightValue Right boundary value of the subtree to move.
     */
    private function moveSubtreeToTargetTree(
        mixed $targetNodeTreeValue,
        mixed $currentOwnerTreeValue,
        int $depth,
        int $leftValue,
        int $positionOffset,
        int $rightValue,
    ): void {
        $this->getOwner()::updateAll(
            [
                $this->leftAttribute => new Expression(
                    $this->getDb()->quoteColumnName($this->leftAttribute) . sprintf('%+d', $positionOffset),
                ),
                $this->rightAttribute => new Expression(
                    $this->getDb()->quoteColumnName($this->rightAttribute) . sprintf('%+d', $positionOffset),
                ),
                $this->depthAttribute => new Expression(
                    $this->getDb()->quoteColumnName($this->depthAttribute) . sprintf('%+d', $depth),
                ),
                $this->treeAttribute => $targetNodeTreeValue,
            ],
            [
                'and',
                [
                    '>=',
                    $this->leftAttribute,
                    $leftValue,
                ],
                [
                    '<=',
                    $this->rightAttribute,
                    $rightValue,
                ],
                [
                    $this->treeAttribute => $currentOwnerTreeValue,
                ],
            ],
        );
    }

    /**
     * Resets the internal operation state after completing a nested set operation.
     *
     * Clears the current operation type and target node reference to prepare for subsequent operations..
     */
    private function resetOperationState(): void
    {
        $this->operation = null;
        $this->node = null;
    }
}
