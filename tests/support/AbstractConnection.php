<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

abstract class AbstractConnection
{
    final public function __construct(public readonly string $dsn = '') {}
}
