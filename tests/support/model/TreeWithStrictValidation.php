<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;

/**
 * Active Record model for testing strict validation rules with nested sets behavior.
 *
 * This class attaches the {@see NestedSetsBehavior} to enable hierarchical data structure operations for a single tree,
 * with additional strict validation rules applied to the name attribute.
 *
 * The model is designed for testing property resolution, behavior integration, and validation scenarios involving a
 * single tree with custom validation requirements.
 *
 * It ensures proper type inference and property access when behaviors are attached to Yii Active Record models, and
 * enforces strict validation for the name property, including required, minimum length, and pattern constraints.
 *
 * Key features:
 * - Behavior attachment for hierarchical data structures with single tree support.
 * - Nested sets model functionality for tree operations.
 * - Property resolution testing for behavior integration.
 * - Static analysis validation for behavior property access.
 * - Strict validation rules for the name attribute (required, minimum length, pattern).
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
final class TreeWithStrictValidation extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'nestedSetsBehavior' => NestedSetsBehavior::class,
        ];
    }

    /**
     * @phpstan-return TreeQuery<self>
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
     * @phpstan-return array<string, int>
     */
    public function transactions(): array
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}
