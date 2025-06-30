<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use LogicException;
use yii\base\Behavior;
use yii\db\{ActiveQuery, Expression};

/**
 * Behavior for {@see ActiveQuery} to support nested sets tree queries.
 *
 * Provides query methods for retrieving root and leaf nodes in a nested sets tree structure using Yii
 * {@see ActiveQuery}.
 *
 * This behavior is designed to be attached to an {@see ActiveQuery} instance for models implementing the nested sets
 * pattern.
 *
 * It enables convenient retrieval of root nodes (nodes with left attribute equal to `1`) and leaf nodes (nodes where
 * right = left + `1`), supporting optional tree attribute sorting for multi-tree scenarios.
 *
 * Key features.
 * - Compatible with Yii {@see ActiveQuery} and {@see Expression} for advanced query building.
 * - Query for leaf nodes (nodes without children).
 * - Query for root nodes in a nested set.
 * - Supports custom left, right, and tree attribute names as defined in the model.
 *
 * @phpstan-template T of ActiveQuery
 *
 * @phpstan-extends Behavior<T>
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
class NestedSetsQueryBehavior extends Behavior
{
    /**
     * Retrieves all leaf nodes (nodes without children) in the nested sets tree.
     *
     * Selects nodes where the right attribute equals the left attribute plus one, indicating that the node has no
     * children.
     *
     * The result is ordered by the left attribute, and if the tree attribute is enabled, by the tree attribute as well.
     *
     * This method is useful for efficiently fetching all terminal nodes in a hierarchical structure managed by the
     * nested sets pattern.
     *
     * @return ActiveQuery Query instance with leaf node conditions applied.
     *
     * Usage example:
     * ```php
     * // Get all leaf nodes in the tree
     * $leaves = $model::find()->leaves()->all();
     * ```
     *
     * @phpstan-return T
     */
    public function leaves(): ActiveQuery
    {
        $class = $this->getOwner()->modelClass;

        $model = new $class();

        $db = $model::getDb();

        $columns = [$model->leftAttribute => SORT_ASC];

        if ($model->treeAttribute !== false) {
            $columns = [$model->treeAttribute => SORT_ASC] + $columns;
        }

        $this->getOwner()
            ->andWhere(
                [$model->rightAttribute => new Expression(
                    $db->quoteColumnName($model->leftAttribute) . '+ 1',
                )],
            )
            ->addOrderBy($columns);

        return $this->getOwner();
    }

    /**
     * Retrieves all root nodes in the nested sets tree.
     *
     * Selects nodes where the left attribute equals `1`, indicating root nodes in the hierarchical structure.
     *
     * The result is ordered by the left attribute, and if the tree attribute is enabled, by the tree attribute as well.
     *
     * This method is useful for efficiently fetching all root nodes in single-tree or multi-tree scenarios managed by
     * the nested sets pattern.
     *
     * @return ActiveQuery Query instance with root node conditions applied.
     *
     * Usage example:
     * ```php
     * // Get all root nodes in the tree
     * $roots = $model::find()->roots()->all();
     * ```
     *
     * @phpstan-return T
     */
    public function roots(): ActiveQuery
    {
        $class = $this->getOwner()->modelClass;
        $model = new $class();

        $columns = [$model->leftAttribute => SORT_ASC];

        if ($model->treeAttribute !== false) {
            $columns = [$model->treeAttribute => SORT_ASC] + $columns;
        }

        $activeQuery = $this->getOwner()->andWhere([$model->leftAttribute => 1]);
        $activeQuery->addOrderBy($columns);

        return $activeQuery;
    }

    /**
     * Returns the {@see ActiveQuery} instance that owns this behavior.
     *
     * Ensures that the behavior has a valid owner before performing any operations that require access to the query
     * instance.
     *
     * This method is used internally by all operations that manipulate the nested set structure, providing type safety
     * and a clear error if the behavior is not attached.
     *
     * @throws LogicException if the behavior is not attached to an owner instance.
     *
     * @return ActiveQuery Owner query instance to which this behavior is attached.
     *
     * @phpstan-return T
     */
    private function getOwner(): ActiveQuery
    {
        if ($this->owner === null) {
            throw new LogicException('The "owner" property must be set before using the behavior.');
        }

        return $this->owner;
    }
}
