<?php

namespace yii2\extensions\nestedsets\tests\support\stub;

use yii\console\controllers\MigrateController;

final class EchoMigrateController extends MigrateController
{
    public function stdout($string)
    {
        echo $string;

        return true;
    }
}
