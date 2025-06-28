<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * @property int $id
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property string $name
 */
final class Tree extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tree}}';
    }

    public function behaviors(): array
    {
        return [
            'nestedSetsBehavior' => NestedSetsBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            ['name', 'required'],
        ];
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

    /**
     * @phpstan-return TreeQuery<self>
     */
    public static function find(): TreeQuery
    {
        return new TreeQuery(self::class);
    }
}
