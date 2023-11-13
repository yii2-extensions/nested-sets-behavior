<?php

declare(strict_types=1);

/**
 * @link https://github.com/creocoder/yii2-nested-sets
 *
 * @copyright Copyright (c) 2015 Alexander Kochetov
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace yii\behavior\nested\sets;

use yii\base\Behavior;
use yii\base\NotSupportedException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;

/**
 * NestedSetsBehavior
 *
 * @property ActiveRecord $owner
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 */
class NestedSetsBehavior extends Behavior
{
    public const OPERATION_MAKE_ROOT = 'makeRoot';
    public const OPERATION_PREPEND_TO = 'prependTo';
    public const OPERATION_APPEND_TO = 'appendTo';
    public const OPERATION_INSERT_BEFORE = 'insertBefore';
    public const OPERATION_INSERT_AFTER = 'insertAfter';
    public const OPERATION_DELETE_WITH_CHILDREN = 'deleteWithChildren';

    /**
     * @var false|string
     */
    public string|false $treeAttribute = false;
    /**
     * @var string
     */
    public string $leftAttribute = 'lft';
    /**
     * @var string
     */
    public string $rightAttribute = 'rgt';
    /**
     * @var string
     */
    public string $depthAttribute = 'depth';
    /**
     * @var string|null
     */
    protected string|null $operation = null;
    /**
     * @var ActiveRecord|null
     */
    protected ActiveRecord|null $node = null;

    /**
     * @inheritdoc
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
     * Creates the root node if the active record is new or moves it
     * as the root node.
     *
     * @param bool $runValidation
     * @param array|null $attributes
     *
     * @return bool
     */
    public function makeRoot(bool $runValidation = true, array $attributes = null): bool
    {
        $this->operation = self::OPERATION_MAKE_ROOT;

        return $this->owner->save($runValidation, $attributes);
    }

    /**
     * Creates a node as the first child of the target node if the active
     * record is new or moves it as the first child of the target node.
     *
     * @param ActiveRecord $node
     * @param bool $runValidation
     * @param array|null $attributes
     *
     * @return bool
     */
    public function prependTo(ActiveRecord $node, bool $runValidation = true, array $attributes = null): bool
    {
        $this->operation = self::OPERATION_PREPEND_TO;
        $this->node = $node;

        return $this->owner->save($runValidation, $attributes);
    }

    /**
     * Creates a node as the last child of the target node if the active
     * record is new or moves it as the last child of the target node.
     *
     * @param ActiveRecord $node
     * @param bool $runValidation
     * @param array|null $attributes
     *
     * @return bool
     */
    public function appendTo(ActiveRecord $node, bool $runValidation = true, array $attributes = null): bool
    {
        $this->operation = self::OPERATION_APPEND_TO;
        $this->node = $node;

        return $this->owner->save($runValidation, $attributes);
    }

    /**
     * Creates a node as the previous sibling of the target node if the active
     * record is new or moves it as the previous sibling of the target node.
     *
     * @param ActiveRecord $node
     * @param bool $runValidation
     * @param array|null $attributes
     *
     * @return bool
     */
    public function insertBefore(ActiveRecord $node, bool $runValidation = true, array $attributes = null): bool
    {
        $this->operation = self::OPERATION_INSERT_BEFORE;
        $this->node = $node;

        return $this->owner->save($runValidation, $attributes);
    }

    /**
     * Creates a node as the next sibling of the target node if the active
     * record is new or moves it as the next sibling of the target node.
     *
     * @param ActiveRecord $node
     * @param bool $runValidation
     * @param array|null $attributes
     *
     * @return bool
     */
    public function insertAfter(ActiveRecord $node, bool $runValidation = true, array $attributes = null): bool
    {
        $this->operation = self::OPERATION_INSERT_AFTER;
        $this->node = $node;

        return $this->owner->save($runValidation, $attributes);
    }

    /**
     * Deletes a node and its children.
     *
     * @throws \Exception
     *
     * @return false|int the number of rows deleted or false if
     * the deletion is unsuccessful for some reason.
     */
    public function deleteWithChildren(): bool|int
    {
        $this->operation = self::OPERATION_DELETE_WITH_CHILDREN;

        if (!$this->owner->isTransactional(ActiveRecord::OP_DELETE)) {
            return $this->deleteWithChildrenInternal();
        }

        $transaction = $this->owner->getDb()->beginTransaction();

        try {
            $result = $this->deleteWithChildrenInternal();

            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }

            return $result;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @return false|int the number of rows deleted or false if
     * the deletion is unsuccessful for some reason.
     */
    protected function deleteWithChildrenInternal(): bool|int
    {
        if (!$this->owner->beforeDelete()) {
            return false;
        }

        $condition = [
            'and',
            ['>=', $this->leftAttribute, $this->owner->getAttribute($this->leftAttribute)],
            ['<=', $this->rightAttribute, $this->owner->getAttribute($this->rightAttribute)],
        ];

        $this->applyTreeAttributeCondition($condition);
        $result = $this->owner->deleteAll($condition);
        $this->owner->setOldAttributes(null);
        $this->owner->afterDelete();

        return $result;
    }

    /**
     * Gets the parents of the node.
     *
     * @param int|null $depth the depth
     *
     * @return ActiveQuery
     */
    public function parents(int $depth = null): ActiveQuery
    {
        $condition = [
            'and',
            ['<', $this->leftAttribute, $this->owner->getAttribute($this->leftAttribute)],
            ['>', $this->rightAttribute, $this->owner->getAttribute($this->rightAttribute)],
        ];

        if ($depth !== null) {
            $condition[] = ['>=', $this->depthAttribute, $this->owner->getAttribute($this->depthAttribute) - $depth];
        }

        $this->applyTreeAttributeCondition($condition);

        return $this->owner->find()->andWhere($condition)->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Gets the children of the node.
     *
     * @param int|null $depth the depth
     *
     * @return ActiveQuery
     */
    public function children(int $depth = null): ActiveQuery
    {
        $condition = [
            'and',
            ['>', $this->leftAttribute, $this->owner->getAttribute($this->leftAttribute)],
            ['<', $this->rightAttribute, $this->owner->getAttribute($this->rightAttribute)],
        ];

        if ($depth !== null) {
            $condition[] = ['<=', $this->depthAttribute, $this->owner->getAttribute($this->depthAttribute) + $depth];
        }

        $this->applyTreeAttributeCondition($condition);

        return $this->owner->find()->andWhere($condition)->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Gets the leaves of the node.
     *
     * @return ActiveQuery
     */
    public function leaves(): ActiveQuery
    {
        $condition = [
            'and',
            ['>', $this->leftAttribute, $this->owner->getAttribute($this->leftAttribute)],
            ['<', $this->rightAttribute, $this->owner->getAttribute($this->rightAttribute)],
            [$this->rightAttribute => new Expression($this->owner->getDb()->quoteColumnName($this->leftAttribute) . '+ 1')],
        ];

        $this->applyTreeAttributeCondition($condition);

        return $this->owner->find()->andWhere($condition)->addOrderBy([$this->leftAttribute => SORT_ASC]);
    }

    /**
     * Gets the previous sibling of the node.
     *
     * @return ActiveQuery
     */
    public function prev(): ActiveQuery
    {
        $condition = [$this->rightAttribute => $this->owner->getAttribute($this->leftAttribute) - 1];
        $this->applyTreeAttributeCondition($condition);

        return $this->owner->find()->andWhere($condition);
    }

    /**
     * Gets the next sibling of the node.
     *
     * @return ActiveQuery
     */
    public function next(): ActiveQuery
    {
        $condition = [$this->leftAttribute => $this->owner->getAttribute($this->rightAttribute) + 1];
        $this->applyTreeAttributeCondition($condition);

        return $this->owner->find()->andWhere($condition);
    }

    /**
     * Determines whether the node is root.
     *
     * @return bool whether the node is root
     */
    public function isRoot(): bool
    {
        return $this->owner->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * Determines whether the node is the child of the parent node.
     *
     * @param ActiveRecord $node the parent node
     *
     * @return bool whether the node is child of the parent node
     */
    public function isChildOf(ActiveRecord $node): bool
    {
        $result = $this->owner->getAttribute($this->leftAttribute) > $node->getAttribute($this->leftAttribute)
            && $this->owner->getAttribute($this->rightAttribute) < $node->getAttribute($this->rightAttribute);

        if ($result && $this->treeAttribute !== false) {
            $result = $this->owner->getAttribute($this->treeAttribute) === $node->getAttribute($this->treeAttribute);
        }

        return $result;
    }

    /**
     * Determines whether the node is leaf.
     *
     * @return bool whether the node is leafed
     */
    public function isLeaf(): bool
    {
        return $this->owner->getAttribute($this->rightAttribute) - $this->owner->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function beforeInsert(): void
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }

        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                $this->beforeInsertRootNode();
                break;
            case self::OPERATION_PREPEND_TO:
                $this->beforeInsertNode($this->node->getAttribute($this->leftAttribute) + 1, 1);
                break;
            case self::OPERATION_APPEND_TO:
                $this->beforeInsertNode($this->node->getAttribute($this->rightAttribute), 1);
                break;
            case self::OPERATION_INSERT_BEFORE:
                $this->beforeInsertNode($this->node->getAttribute($this->leftAttribute), 0);
                break;
            case self::OPERATION_INSERT_AFTER:
                $this->beforeInsertNode($this->node->getAttribute($this->rightAttribute) + 1, 0);
                break;
            default:
                throw new NotSupportedException('Method "' . get_class($this->owner) .
                    '::insert" is not supported for inserting new nodes.');
        }
    }

    /**
     * @throws Exception
     */
    protected function beforeInsertRootNode(): void
    {
        if ($this->treeAttribute === false && $this->owner->find()->roots()->exists()) {
            throw new Exception('Can not create more than one root when "treeAttribute" is false.');
        }

        $this->owner->setAttribute($this->leftAttribute, 1);
        $this->owner->setAttribute($this->rightAttribute, 2);
        $this->owner->setAttribute($this->depthAttribute, 0);
    }

    /**
     * @param int $value
     * @param int $depth
     *
     * @throws Exception
     */
    protected function beforeInsertNode(int|null $value, int $depth): void
    {
        if ($this->node->getIsNewRecord()) {
            throw new Exception('Can not create a node when the target node is new record.');
        }

        if ($depth === 0 && $this->node->isRoot()) {
            throw new Exception('Can not create a node when the target node is root.');
        }

        $this->owner->setAttribute($this->leftAttribute, $value);
        $this->owner->setAttribute($this->rightAttribute, $value + 1);
        $this->owner->setAttribute($this->depthAttribute, $this->node->getAttribute($this->depthAttribute) + $depth);

        if ($this->treeAttribute !== false) {
            $this->owner->setAttribute($this->treeAttribute, $this->node->getAttribute($this->treeAttribute));
        }

        $this->shiftLeftRightAttribute($value, 2);
    }

    /**
     * @throws Exception
     */
    public function afterInsert(): void
    {
        if ($this->operation === self::OPERATION_MAKE_ROOT && $this->treeAttribute !== false) {
            $this->owner->setAttribute($this->treeAttribute, $this->owner->getPrimaryKey());
            $primaryKey = $this->owner->primaryKey();

            if (!isset($primaryKey[0])) {
                throw new Exception('"' . get_class($this->owner) . '" must have a primary key.');
            }

            $this->owner->updateAll(
                [$this->treeAttribute => $this->owner->getAttribute($this->treeAttribute)],
                [$primaryKey[0] => $this->owner->getAttribute($this->treeAttribute)]
            );
        }

        $this->operation = null;
        $this->node = null;
    }

    /**
     * @throws Exception
     */
    public function beforeUpdate(): void
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }

        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                if ($this->treeAttribute === false) {
                    throw new Exception('Can not move a node as the root when "treeAttribute" is false.');
                }

                if ($this->owner->isRoot()) {
                    throw new Exception('Can not move the root node as the root.');
                }

                break;
            case self::OPERATION_INSERT_BEFORE:
            case self::OPERATION_INSERT_AFTER:
                if ($this->node->isRoot()) {
                    throw new Exception('Can not move a node when the target node is root.');
                }
                // no break
            case self::OPERATION_PREPEND_TO:
            case self::OPERATION_APPEND_TO:
                if ($this->node->getIsNewRecord()) {
                    throw new Exception('Can not move a node when the target node is new record.');
                }

                if ($this->owner->equals($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }

                if ($this->node->isChildOf($this->owner)) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
        }
    }

    public function afterUpdate(): void
    {
        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                $this->moveNodeAsRoot();
                break;
            case self::OPERATION_PREPEND_TO:
                $this->moveNode($this->node->getAttribute($this->leftAttribute) + 1, 1);
                break;
            case self::OPERATION_APPEND_TO:
                $this->moveNode($this->node->getAttribute($this->rightAttribute), 1);
                break;
            case self::OPERATION_INSERT_BEFORE:
                $this->moveNode($this->node->getAttribute($this->leftAttribute), 0);
                break;
            case self::OPERATION_INSERT_AFTER:
                $this->moveNode($this->node->getAttribute($this->rightAttribute) + 1, 0);
                break;
            default:
                return;
        }

        $this->operation = null;
        $this->node = null;
    }

    protected function moveNodeAsRoot(): void
    {
        $db = $this->owner->getDb();
        $leftValue = $this->owner->getAttribute($this->leftAttribute);
        $rightValue = $this->owner->getAttribute($this->rightAttribute);
        $depthValue = $this->owner->getAttribute($this->depthAttribute);
        $treeValue = $this->owner->getAttribute($this->treeAttribute);
        $leftAttribute = $db->quoteColumnName($this->leftAttribute);
        $rightAttribute = $db->quoteColumnName($this->rightAttribute);
        $depthAttribute = $db->quoteColumnName($this->depthAttribute);

        $this->owner->updateAll(
            [
                $this->leftAttribute => new Expression($leftAttribute . sprintf('%+d', 1 - $leftValue)),
                $this->rightAttribute => new Expression($rightAttribute . sprintf('%+d', 1 - $leftValue)),
                $this->depthAttribute => new Expression($depthAttribute . sprintf('%+d', -$depthValue)),
                $this->treeAttribute => $this->owner->getPrimaryKey(),
            ],
            [
                'and',
                ['>=', $this->leftAttribute, $leftValue],
                ['<=', $this->rightAttribute, $rightValue],
                [$this->treeAttribute => $treeValue],
            ]
        );

        $this->shiftLeftRightAttribute($rightValue + 1, $leftValue - $rightValue - 1);
    }

    /**
     * @param int $value
     * @param int $depth
     */
    protected function moveNode(int $value, int $depth): void
    {
        $db = $this->owner->getDb();
        $leftValue = $this->owner->getAttribute($this->leftAttribute);
        $rightValue = $this->owner->getAttribute($this->rightAttribute);
        $depthValue = $this->owner->getAttribute($this->depthAttribute);
        $depthAttribute = $db->quoteColumnName($this->depthAttribute);
        $depth = $this->node->getAttribute($this->depthAttribute) - $depthValue + $depth;

        if ($this->treeAttribute === false
            || $this->owner->getAttribute($this->treeAttribute) === $this->node->getAttribute($this->treeAttribute)) {
            $delta = $rightValue - $leftValue + 1;
            $this->shiftLeftRightAttribute($value, $delta);

            if ($leftValue >= $value) {
                $leftValue += $delta;
                $rightValue += $delta;
            }

            $condition = ['and', ['>=', $this->leftAttribute, $leftValue], ['<=', $this->rightAttribute, $rightValue]];
            $this->applyTreeAttributeCondition($condition);

            $this->owner->updateAll(
                [$this->depthAttribute => new Expression($depthAttribute . sprintf('%+d', $depth))],
                $condition
            );

            foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
                $condition = ['and', ['>=', $attribute, $leftValue], ['<=', $attribute, $rightValue]];
                $this->applyTreeAttributeCondition($condition);

                $this->owner->updateAll(
                    [$attribute => new Expression($db->quoteColumnName($attribute) . sprintf('%+d', $value - $leftValue))],
                    $condition
                );
            }

            $this->shiftLeftRightAttribute($rightValue + 1, -$delta);
        } else {
            $leftAttribute = $db->quoteColumnName($this->leftAttribute);
            $rightAttribute = $db->quoteColumnName($this->rightAttribute);
            $nodeRootValue = $this->node->getAttribute($this->treeAttribute);

            foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
                $this->owner->updateAll(
                    [$attribute => new Expression($db->quoteColumnName($attribute) . sprintf('%+d', $rightValue - $leftValue + 1))],
                    ['and', ['>=', $attribute, $value], [$this->treeAttribute => $nodeRootValue]]
                );
            }

            $delta = $value - $leftValue;

            $this->owner->updateAll(
                [
                    $this->leftAttribute => new Expression($leftAttribute . sprintf('%+d', $delta)),
                    $this->rightAttribute => new Expression($rightAttribute . sprintf('%+d', $delta)),
                    $this->depthAttribute => new Expression($depthAttribute . sprintf('%+d', $depth)),
                    $this->treeAttribute => $nodeRootValue,
                ],
                [
                    'and',
                    ['>=', $this->leftAttribute, $leftValue],
                    ['<=', $this->rightAttribute, $rightValue],
                    [$this->treeAttribute => $this->owner->getAttribute($this->treeAttribute)],
                ]
            );

            $this->shiftLeftRightAttribute($rightValue + 1, $leftValue - $rightValue - 1);
        }
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function beforeDelete(): void
    {
        if ($this->owner->getIsNewRecord()) {
            throw new Exception('Can not delete a node when it is new record.');
        }

        if ($this->owner->isRoot() && $this->operation !== self::OPERATION_DELETE_WITH_CHILDREN) {
            throw new NotSupportedException('Method "' . get_class($this->owner) . '::delete" is not supported for deleting root nodes.');
        }

        $this->owner->refresh();
    }

    public function afterDelete(): void
    {
        $leftValue = $this->owner->getAttribute($this->leftAttribute);
        $rightValue = $this->owner->getAttribute($this->rightAttribute);

        if ($this->owner->isLeaf() || $this->operation === self::OPERATION_DELETE_WITH_CHILDREN) {
            $this->shiftLeftRightAttribute($rightValue + 1, $leftValue - $rightValue - 1);
        } else {
            $condition = [
                'and',
                ['>=', $this->leftAttribute, $this->owner->getAttribute($this->leftAttribute)],
                ['<=', $this->rightAttribute, $this->owner->getAttribute($this->rightAttribute)],
            ];

            $this->applyTreeAttributeCondition($condition);
            $db = $this->owner->getDb();

            $this->owner->updateAll(
                [
                    $this->leftAttribute => new Expression($db->quoteColumnName($this->leftAttribute) . sprintf('%+d', -1)),
                    $this->rightAttribute => new Expression($db->quoteColumnName($this->rightAttribute) . sprintf('%+d', -1)),
                    $this->depthAttribute => new Expression($db->quoteColumnName($this->depthAttribute) . sprintf('%+d', -1)),
                ],
                $condition
            );

            $this->shiftLeftRightAttribute($rightValue + 1, -2);
        }

        $this->operation = null;
        $this->node = null;
    }

    /**
     * @param int $value
     * @param int $delta
     */
    protected function shiftLeftRightAttribute(int $value, int $delta): void
    {
        $db = $this->owner->getDb();

        foreach ([$this->leftAttribute, $this->rightAttribute] as $attribute) {
            $condition = ['>=', $attribute, $value];
            $this->applyTreeAttributeCondition($condition);

            $this->owner->updateAll(
                [$attribute => new Expression($db->quoteColumnName($attribute) . sprintf('%+d', $delta))],
                $condition
            );
        }
    }

    /**
     * @param array $condition
     */
    protected function applyTreeAttributeCondition(array &$condition): void
    {
        if ($this->treeAttribute !== false) {
            $condition = [
                'and',
                $condition,
                [$this->treeAttribute => $this->owner->getAttribute($this->treeAttribute)],
            ];
        }
    }
}
