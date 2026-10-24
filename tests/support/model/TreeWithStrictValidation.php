<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * Active Record model with strict validation rules used by the behavior tests.
 *
 * @property int $depth
 * @property int $id
 * @property int $lft
 * @property int $rgt
 * @property string $name
 */
final class TreeWithStrictValidation extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'nestedSetsBehavior' => NestedSetsBehavior::class,
        ];
    }

    /**
     * @return TreeQuery<self>
     */
    public static function find(): TreeQuery
    {
        return new TreeQuery(self::class);
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
            ['name', 'required', 'message' => 'Name cannot be blank.'],
            ['name', 'string', 'min' => 5, 'message' => 'Name must be at least 5 characters long.'],
            ['name', 'match', 'pattern' => '/^[A-Z]/', 'message' => 'Name must start with an uppercase letter.'],
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
