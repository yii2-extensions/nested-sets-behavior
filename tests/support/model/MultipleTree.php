<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * @property int $id
 * @property int $depth
 * @property int $lft
 * @property int $rgt
 * @property int $tree
 * @property string $name
 */
class MultipleTree extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'nestedSetsBehavior' => [
                'class' => NestedSetsBehavior::class,
                'treeAttribute' => 'tree',
            ],
        ];
    }

    /**
     * @phpstan-return MultipleTreeQuery<static>
     */
    public static function find(): MultipleTreeQuery
    {
        return new MultipleTreeQuery(static::class);
    }

    public function rules(): array
    {
        return [
            ['name', 'required'],
        ];
    }
    public static function tableName(): string
    {
        return '{{%multiple_tree}}';
    }

    /**
     * @phpstan-return array<string, int>
     */
    public function transactions(): array
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}
