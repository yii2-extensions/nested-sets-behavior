<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\tests\support\stub\ExtendableNestedSetsBehavior;

/**
 * Active Record model with multiple trees that attaches {@see ExtendableNestedSetsBehavior} for the extensibility
 * tests.
 *
 * @property int $depth
 * @property int $id
 * @property int $lft
 * @property int $rgt
 * @property int $tree
 * @property string $name
 */
final class ExtendableMultipleTree extends ActiveRecord
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
     * @return ExtendableMultipleTreeQuery<self>
     */
    public static function find(): ExtendableMultipleTreeQuery
    {
        return new ExtendableMultipleTreeQuery(self::class);
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
     * @return array<string, int>
     */
    public function transactions(): array
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}
