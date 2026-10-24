<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

/**
 * Active Record model that addresses the fixture tree table through a configurable prefixed or schema-qualified name.
 */
final class ConcurrencyTree extends Tree
{
    public static string $table = '{{%tree}}';

    public static function tableName(): string
    {
        return self::$table;
    }
}
