<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * Active Record model with a single tree used by the behavior tests.
 *
 * @property int $depth
 * @property int $id
 * @property int $lft
 * @property int $rgt
 * @property string $name
 */
class Tree extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'nestedSetsBehavior' => NestedSetsBehavior::class,
        ];
    }

    /**
     * @return TreeQuery<static>
     */
    public static function find(): TreeQuery
    {
        return new TreeQuery(static::class);
    }

    public function rules(): array
    {
        return [
            ['name', 'required'],
        ];
    }

    public static function tableName(): string
    {
        return '{{%tree}}';
    }

    /**
     * @return array<string, int>
     */
    public function transactions(): array
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}
