<?php

declare(strict_types=1);

/**
 * @link https://github.com/creocoder/yii2-nested-sets
 *
 * @copyright Copyright (c) 2015 Alexander Kochetov
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace yii\behavior\nested\sets\tests\models;

use yii\behavior\nested\sets\NestedSetsBehavior;

/**
 * MultipleTree
 *
 * @property int $id
 * @property int $tree
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property string $name
 */
final class MultipleTree extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%multiple_tree}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => NestedSetsBehavior::class,
                'treeAttribute' => 'tree',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['name', 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return new MultipleTreeQuery(self::class);
    }
}
