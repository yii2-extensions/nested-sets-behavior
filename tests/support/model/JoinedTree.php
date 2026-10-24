<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

/**
 * Active Record model that loads nodes through an aliased self-join with duplicate column names.
 */
final class JoinedTree extends Tree
{
    /**
     * @return TreeQuery<static>
     */
    public static function find(): TreeQuery
    {
        return parent::find()
            ->alias('node')
            ->leftJoin(['mirror' => self::tableName()], '[[mirror.id]] = [[node.id]]');
    }
}
