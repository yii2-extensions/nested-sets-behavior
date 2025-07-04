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
class Tree extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'nestedSetsBehavior' => NestedSetsBehavior::class,
        ];
    }

    /**
     * @phpstan-return TreeQuery<static>
     */
    public static function find(): TreeQuery
    {
        return new TreeQuery(static::class);
    }

    public function isTransactional($operation): bool
    {
        if ($operation === ActiveRecord::OP_DELETE) {
            return false;
        }

        return parent::isTransactional($operation);
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
     * @phpstan-return array<string, int>
     */
    public function transactions(): array
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}
