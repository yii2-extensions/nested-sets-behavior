<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use LogicException;
use yii\base\Behavior;
use yii\db\{ActiveQuery, ActiveRecord, Expression};
use yii2\extensions\nestedsets\exception\Message;

/**
 * Adds root and leaf queries to an {@see ActiveQuery} whose model is managed by {@see NestedSetsBehavior}.
 *
 * @template T of ActiveQuery
 *
 * @extends Behavior<T>
 */
class NestedSetsQueryBehavior extends Behavior
{
    /**
     * Restricts the owner query to leaf nodes, ordered by tree identifier when enabled and then by left value.
     *
     * @throws LogicException if the behavior is not attached or the query model is not an {@see ActiveRecord}.
     *
     * @return T Owner query with the leaf condition and ordering applied.
     */
    public function leaves(): ActiveQuery
    {
        $model = $this->createModel();

        $db = $model::getDb();

        $columns = [$model->leftAttribute => SORT_ASC];

        if ($model->treeAttribute !== false) {
            $columns = [$model->treeAttribute => SORT_ASC] + $columns;
        }

        $this->getOwner()
            ->andWhere([$model->rightAttribute => new Expression($db->quoteColumnName($model->leftAttribute) . '+ 1')])
            ->addOrderBy($columns);

        return $this->getOwner();
    }

    /**
     * Restricts the owner query to root nodes, ordered by tree identifier when enabled and then by left value.
     *
     * @throws LogicException if the behavior is not attached or the query model is not an {@see ActiveRecord}.
     *
     * @return T Owner query with the root condition and ordering applied.
     */
    public function roots(): ActiveQuery
    {
        $model = $this->createModel();

        $columns = [$model->leftAttribute => SORT_ASC];

        if ($model->treeAttribute !== false) {
            $columns = [$model->treeAttribute => SORT_ASC] + $columns;
        }

        $activeQuery = $this->getOwner()->andWhere([$model->leftAttribute => 1]);

        $activeQuery->addOrderBy($columns);

        return $activeQuery;
    }

    /**
     * Instantiates the query model to read its nested set attribute configuration.
     *
     * @throws LogicException if the model class is not an {@see ActiveRecord}.
     *
     * @return ActiveRecord Instantiated query model.
     */
    private function createModel(): ActiveRecord
    {
        $class = $this->getOwner()->modelClass;

        $model = new $class();

        if ($model instanceof ActiveRecord === false) {
            throw new LogicException(
                Message::MODEL_NOT_ACTIVE_RECORD->getMessage(),
            );
        }

        return $model;
    }

    /**
     * Returns the query the behavior is attached to.
     *
     * @throws LogicException if the behavior is not attached.
     *
     * @return T Owner query.
     */
    private function getOwner(): ActiveQuery
    {
        if ($this->owner === null) {
            throw new LogicException(Message::BEHAVIOR_NOT_ATTACHED->getMessage());
        }

        return $this->owner;
    }
}
