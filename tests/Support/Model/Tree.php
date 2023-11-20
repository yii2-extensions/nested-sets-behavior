<?php

declare(strict_types=1);

namespace Yii2\Extensions\NestedSets\Tests\Support\Model;

use Yii2\Extensions\NestedSets\NestedSetsBehavior;

/**
 * Tree
 *
 * @property int $id
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property string $name
 */
final class Tree extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%tree}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            NestedSetsBehavior::class,
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
        return new TreeQuery(self::class);
    }
}
