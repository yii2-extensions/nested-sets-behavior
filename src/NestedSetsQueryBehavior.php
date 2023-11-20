<?php

declare(strict_types=1);

namespace Yii2\Extensions\NestedSets;

use yii\base\Behavior;
use yii\db\ActiveQuery;
use yii\db\Expression;

class NestedSetsQueryBehavior extends Behavior
{
    /**
     * Gets the root nodes.
     *
     * @return ActiveQuery the owner
     */
    public function roots(): ActiveQuery
    {
        $model = new $this->owner->modelClass();

        $this->owner
            ->andWhere([$model->leftAttribute => 1])
            ->addOrderBy([$model->primaryKey()[0] => SORT_ASC]);

        return $this->owner;
    }

    /**
     * Gets the leaf nodes.
     *
     * @return ActiveQuery the owner
     */
    public function leaves(): ActiveQuery
    {
        $model = new $this->owner->modelClass();
        $db = $model->getDb();

        $columns = [$model->leftAttribute => SORT_ASC];

        if ($model->treeAttribute !== false) {
            $columns = [$model->treeAttribute => SORT_ASC] + $columns;
        }

        $this->owner
            ->andWhere(
                [$model->rightAttribute => new Expression(
                    $db->quoteColumnName($model->leftAttribute) . '+ 1'
                )]
            )
            ->addOrderBy($columns);

        return $this->owner;
    }
}
