<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

use yii\db\{Command, Connection};

/**
 * Database connection that reports a configurable MySQL `lower_case_table_names` mode to the writer lock tests.
 */
final class ReportedCaseModeConnection extends Connection
{
    public int $reportedCaseMode = 0;

    /**
     * @param string|null $sql
     * @param array<array-key, mixed> $params
     */
    public function createCommand($sql = null, $params = []): Command
    {
        if ($sql === 'SELECT @@lower_case_table_names') {
            return parent::createCommand('SELECT CAST(:mode AS SIGNED)', [':mode' => $this->reportedCaseMode]);
        }

        return parent::createCommand($sql, $params);
    }
}
