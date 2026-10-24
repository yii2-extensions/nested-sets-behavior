<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\model;

/**
 * Active Record model that reports upper-cased storage values as dirty attributes while keeping business values.
 */
final class StorageValueTree extends Tree
{
    /**
     * @param string[]|null $names
     * @return array<array-key, mixed>
     */
    public function getDirtyAttributes($names = null): array
    {
        $attributes = parent::getDirtyAttributes($names);

        if (isset($attributes['name']) && is_string($attributes['name'])) {
            $attributes['name'] = strtoupper($attributes['name']);
        }

        return $attributes;
    }
}
