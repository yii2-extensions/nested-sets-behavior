<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

/**
 * Enum for SQL Server database connection configuration constants.
 *
 * Provides named constants for SQL Server connection parameters used in test suites, including driver name, Data Source
 * Name (DSN), username, and password. This enum centralizes connection details for reuse across multiple test cases,
 * ensuring consistency and simplifying configuration management for SQL Server-based tests.
 *
 * Key features.
 * - Centralizes SQL Server connection parameters for test environments.
 * - Ensures consistent configuration across all SQL Server test cases.
 * - Simplifies updates to connection details for local and CI environments.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
enum MSSQLConnection: string
{
    /**
     * Database driver name.
     */
    case DRIVER_NAME = 'sqlsrv';

    /**
     * Data Source Name (DSN).
     */
    case DSN = 'sqlsrv:Server=127.0.0.1,1433;Database=yiitest;Encrypt=no';

    /**
     * Password for the database connection.
     */
    case PASSWORD = 'YourStrong!Passw0rd';

    /**
     * Username for the database connection.
     */
    case USERNAME = 'SA';
}
