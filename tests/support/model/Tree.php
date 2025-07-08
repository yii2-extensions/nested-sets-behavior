<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * Active Record model for testing single tree support with nested sets behavior.
 *
 * This class attaches the {@see NestedSetsBehavior} to enable hierarchical data structure operations for a single tree.
 *
 * The model provides tree structure functionality through the behavior properties and methods, allowing for nested sets
 * model implementation on Active Record instances.
 *
 * The model is designed for testing property resolution and behavior integration in scenarios involving a single tree.
 *
 * It ensures proper type inference and property access when behaviors are attached to Yii Active Record models.
 *
 * Key features:
 * - Behavior attachment for hierarchical data structures with single tree support.
 * - Nested sets model functionality for tree operations.
 * - Property resolution testing for behavior integration.
 * - Static analysis validation for behavior property access.
 * - Table mapping with the `tree` table.
 *
 * @phpstan-property int $depth
 * @phpstan-property int $id
 * @phpstan-property int $lft
 * @phpstan-property int $rgt
 * @phpstan-property string $name
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
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
