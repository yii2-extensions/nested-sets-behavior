<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

use yii\db\Connection;

/**
 * Enum for supported database connection configurations in test environments.
 *
 * Provides connection settings for various database drivers used in integration and unit tests, enabling consistent and
 * repeatable test execution across multiple database backends.
 *
 * This enum centralizes the configuration for all supported database types, allowing test cases to switch between
 * drivers and ensuring that connection parameters are maintained in a single location for maintainability.
 *
 * Key features.
 * - Centralized configuration for MSSQL, MySQL, Oracle, PostgreSQL, and SQLite test databases.
 * - Ensures consistent connection details for all supported drivers.
 * - Provides connection parameters as associative arrays for use with Yii Database connection classes.
 * - Simplifies test setup and database switching in cross-database test suites.
 *
 * @see Connection for Yii Database connection implementation.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
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
     * Provides an associative array of connection parameters for the selected database type, including driver-specific
     * DSN, credentials, and class reference for Yii Database integration. This method enables test cases to retrieve
     * consistent connection settings for all supported database backends, facilitating cross-database testing and
     * centralized configuration management.
     *
     * The returned array structure matches the requirements of {@see Connection} and can be used directly to
     * instantiate or configure Yii Database connections in test environments.
     *
     * Usage example:
     * ```php
     * $config = DatabaseConnection::MYSQL->connection();
     * ```
     *
     * @return array Connection parameters for the selected database driver.
     *
     * @phpstan-return string[]
     */
    public function connection(): array
    {
        return match ($this) {
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
    }
}
