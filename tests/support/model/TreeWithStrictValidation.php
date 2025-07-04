<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

/**
 * @phpstan-property int $depth
 * @phpstan-property int $id
 * @phpstan-property int $lft
 * @phpstan-property int $rgt
 * @phpstan-property string $name
 */
final class TreeWithStrictValidation extends Tree
{
    public function rules(): array
    {
        return [
            ['name', 'required', 'message' => 'Name cannot be blank.'],
            ['name', 'string', 'min' => 5, 'message' => 'Name must be at least 5 characters long.'],
            ['name', 'match', 'pattern' => '/^[A-Z]/', 'message' => 'Name must start with an uppercase letter.'],
        ];
    }
}
