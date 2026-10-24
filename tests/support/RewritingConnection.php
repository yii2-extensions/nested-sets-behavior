<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

use yii\db\{Command, Connection};

use function str_contains;

/**
 * Database connection that replaces selected statements with configured SQL so tests can report arbitrary results.
 */
final class RewritingConnection extends Connection
{
    /**
     * @var array<string, string> Replacement SQL indexed by the exact statement it replaces.
     */
    public array $exact = [];

    /**
     * @var array<string, string> Replacement SQL indexed by a fragment of the statement it replaces.
     */
    public array $fragments = [];

    /**
     * Replaced statements run without the original parameters, so each replacement must be self-contained.
     *
     * @param string|null $sql
     * @param array<array-key, mixed> $params
     */
    public function createCommand($sql = null, $params = []): Command
    {
        if ($sql !== null) {
            if (isset($this->exact[$sql])) {
                return parent::createCommand($this->exact[$sql]);
            }

            foreach ($this->fragments as $fragment => $replacement) {
                if (str_contains($sql, $fragment)) {
                    return parent::createCommand($replacement);
                }
            }
        }

        return parent::createCommand($sql, $params);
    }
}
