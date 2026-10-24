<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\stub;

use yii\console\controllers\MigrateController;

/**
 * Console migrate controller that echoes its output so tests can capture it.
 */
final class EchoMigrateController extends MigrateController
{
    public function stdout($string): bool
    {
        echo $string;

        return true;
    }
}
