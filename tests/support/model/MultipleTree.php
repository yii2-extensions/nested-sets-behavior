<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * Active Record model for testing multiple tree support with nested sets behavior.
 *
 * This class attaches the {@see NestedSetsBehavior} to enable hierarchical data structure operations with support for
 * multiple trees.
 *
 * The model provides tree structure functionality through the behavior properties and methods, allowing for nested sets
 * model implementation on Active Record instances with a tree attribute.
 *
 * The model is designed for testing property resolution and behavior integration in scenarios involving multiple tree
 * columns.
 *
 * It ensures proper type inference and property access when behaviors are attached to Yii Active Record models with a
 * tree context.
 *
 * Key features:
 * - Behavior attachment for hierarchical data structures with multiple tree support.
 * - Nested sets model functionality for tree operations.
 * - Property resolution testing for behavior integration.
 * - Static analysis validation for behavior property access.
 * - Table mapping with the `multiple_tree` table.
 *
 * @phpstan-property int $depth
 * @phpstan-property int $id
 * @phpstan-property int $lft
 * @phpstan-property int $rgt
 * @phpstan-property int $tree
 * @phpstan-property string $name
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
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
