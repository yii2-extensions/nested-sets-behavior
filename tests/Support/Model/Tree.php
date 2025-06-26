<?php

declare(strict_types=1);

namespace Yii2\Extensions\NestedSets\Tests\Support\Model;

use Yii2\Extensions\NestedSets\NestedSetsBehavior;

/**
 * @property int $id
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property string $name
 */
final class Tree extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return '{{%tree}}';
    }

    public function behaviors()
    {
        return [
            NestedSetsBehavior::class,
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
        return new TreeQuery(self::class);
    }
}
