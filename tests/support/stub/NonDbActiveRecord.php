<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\stub;

use Yii;
use yii\base\NotSupportedException;
use yii\db\{ActiveQuery, ActiveQueryInterface, BaseActiveRecord, Connection};

/**
 * Active Record that does not extend {@see \yii\db\ActiveRecord}, used to test the model class guards.
 */
final class NonDbActiveRecord extends BaseActiveRecord
{
    public static function find(): ActiveQueryInterface
    {
        return new ActiveQuery(self::class);
    }

    public static function getDb(): Connection
    {
        return Yii::$app->getDb();
    }

    /**
     * @param array<array-key, mixed>|null $attributes
     *
     * @throws NotSupportedException because the stub is never persisted.
     */
    public function insert($runValidation = true, $attributes = null): bool
    {
        throw new NotSupportedException('The stub record cannot be inserted.');
    }

    /**
     * @return string[]
     */
    public static function primaryKey(): array
    {
        return ['id'];
    }
}
