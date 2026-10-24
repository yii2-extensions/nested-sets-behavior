<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

use yii\db\Connection;

/**
 * Provides the connection configuration for each database driver used by the test suites.
 *
 * @see Connection for Yii Database connection implementation.
 */
enum DatabaseConnection: string
{
    /**
     * SQL Server database connection configuration.
     */
    case MSSQL = 'sqlsrv';

    /**
     * MySQL database connection configuration.
     */
    case MYSQL = 'mysql';

    /**
     * Oracle database connection configuration.
     */
    case ORACLE = 'oci';

    /**
     * PostgreSQL database connection configuration.
     */
    case PGSQL = 'pgsql';

    /**
     * SQLite database connection configuration.
     */
    case SQLITE = 'sqlite';

    /**
     * Returns the database connection configuration for the current driver.
     *
     * @return string[] Connection parameters for the selected database driver.
     */
    public function connection(): array
    {
        $connection = match ($this) {
            self::MSSQL => [
                'class' => Connection::class,
                'dsn' => 'sqlsrv:Server=127.0.0.1,1433;Database=yiitest;Encrypt=no',
                'password' => 'YourStrong!Passw0rd',
                'username' => 'SA',
            ],
            self::MYSQL => [
                'class' => Connection::class,
                'dsn' => 'mysql:host=127.0.0.1;dbname=yiitest;charset=utf8mb4',
                'password' => 'root',
                'username' => 'root',
            ],
            self::ORACLE => [
                'class' => Connection::class,
                'dsn' => 'oci:dbname=localhost/FREEPDB1;charset=AL32UTF8;',
                'password' => 'root',
                'username' => 'system',
            ],
            self::PGSQL => [
                'class' => Connection::class,
                'dsn' => 'pgsql:host=localhost;dbname=yiitest;port=5432;',
                'password' => 'root',
                'username' => 'root',
            ],
            self::SQLITE => [
                'class' => Connection::class,
                'dsn' => 'sqlite::memory:',
            ],
        };

        foreach (['dsn', 'username', 'password'] as $attribute) {
            $value = getenv('NESTED_SETS_TEST_' . $this->name . '_' . strtoupper($attribute));

            if ($value !== false) {
                if ($attribute === 'dsn') {
                    $token = getenv('TEST_TOKEN');
                    $value = str_replace('{token}', $token === false ? 'initial' : $token, $value);
                }
                $connection[$attribute] = $value;
            }
        }

        return $connection;
    }
}
