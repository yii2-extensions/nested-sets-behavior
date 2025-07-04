<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\tests\support\stub\ExtendableNestedSetsBehavior;

/**
 * @phpstan-property int $depth
 * @phpstan-property int $id
 * @phpstan-property int $lft
 * @phpstan-property int $rgt
 * @phpstan-property int $tree
 * @phpstan-property string $name
 */
class ExtendableMultipleTree extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'nestedSetsBehavior' => [
                'class' => ExtendableNestedSetsBehavior::class,
                'treeAttribute' => 'tree',
            ],
        ];
    }

    /**
     * @phpstan-return ExtendableMultipleTreeQuery<static>
     */
    public static function find(): ExtendableMultipleTreeQuery
    {
        return new ExtendableMultipleTreeQuery(static::class);
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
