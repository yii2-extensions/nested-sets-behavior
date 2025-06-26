<?php

declare(strict_types=1);

namespace Yii2\Extensions\NestedSets\Tests\Support\Model;

use Yii2\Extensions\NestedSets\NestedSetsBehavior;

/**
 * @property int $id
 * @property int $tree
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property string $name
 */
final class MultipleTree extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return '{{%multiple_tree}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => NestedSetsBehavior::class,
                'treeAttribute' => 'tree',
            ],
        ];
    }

    public function rules()
    {
        return [
            ['name', 'required'],
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }

    public static function find()
    {
        return new MultipleTreeQuery(self::class);
    }
}
