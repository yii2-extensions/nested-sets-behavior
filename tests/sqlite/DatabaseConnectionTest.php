<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\sqlite;

use PHPUnit\Framework\Attributes\{Group, TestWith};
use PHPUnit\Framework\TestCase;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

/**
 * Unit tests for the {@see DatabaseConnection} fixture DSN token substitution.
 */
#[Group('sqlite')]
final class DatabaseConnectionTest extends TestCase
{
    #[TestWith([false, 'mysql:dbname=fixture_{token}', 'mysql:dbname=fixture_initial'])]
    #[TestWith(['3', 'mysql:dbname=fixture_{token}', 'mysql:dbname=fixture_3'])]
    #[TestWith(['0', 'mysql:dbname=fixture_{token}', 'mysql:dbname=fixture_0'])]
    #[TestWith(['3', 'mysql:dbname=fixture', 'mysql:dbname=fixture'])]
    public function testTokenizedFixtureDsn(string|false $token, string $dsn, string $expected): void
    {
        $previousDsn = getenv('NESTED_SETS_TEST_MYSQL_DSN');
        $previousToken = getenv('TEST_TOKEN');

        putenv(
            'NESTED_SETS_TEST_MYSQL_DSN=' . $dsn,
        );
        putenv(
            $token === false ? 'TEST_TOKEN' : 'TEST_TOKEN=' . $token,
        );

        try {
            self::assertSame(
                $expected,
                DatabaseConnection::MYSQL->connection()['dsn'] ?? null,
                'Resolved DSN must match the expected value.',
            );
        } finally {
            putenv(
                $previousDsn === false ? 'NESTED_SETS_TEST_MYSQL_DSN' : 'NESTED_SETS_TEST_MYSQL_DSN=' . $previousDsn,
            );
            putenv(
                $previousToken === false ? 'TEST_TOKEN' : 'TEST_TOKEN=' . $previousToken,
            );
        }
    }
}
