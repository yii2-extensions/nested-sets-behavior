<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

final class SqliteConnection extends AbstractConnection
{
    public static function create(): self
    {
        return new self('sqlite::memory:');
    }
}
