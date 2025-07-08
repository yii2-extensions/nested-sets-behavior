<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support\stub;

use yii\console\controllers\MigrateController;

/**
 * Console migrate controller stub that echoes output for testing.
 *
 * Provides a stub implementation of the {@see MigrateController} for use in test environments, overriding the
 * {@see stdout()} method to directly echo output instead of writing to the console output stream.
 *
 * This class is intended for use in automated tests where migration output needs to be captured or asserted without
 * relying on the Yii Console output infrastructure.
 *
 * Key features:
 * - Designed for use in migration-related test scenarios.
 * - Overrides {@see stdout()} to echo output for test assertions.
 * - Simplifies output handling in test environments.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class EchoMigrateController extends MigrateController
{
    public function stdout($string): bool
    {
        echo $string;

        return true;
    }
}
