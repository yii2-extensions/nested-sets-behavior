<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets;

use PDO;
use PDOException;
use Throwable;
use yii\base\{InvalidConfigException, NotSupportedException};
use yii\db\{ActiveQuery, ActiveRecord, Connection, Expression, Query, Transaction};
use yii\db\Exception as DatabaseException;
use yii\db\mssql\TableSchema as MssqlTableSchema;
use yii2\extensions\nestedsets\exception\Message;

use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Serializes the writers of one physical table through a row of the `nested_sets_lock` registry, held until the
 * outermost transaction commits or rolls back.
 *
 * Each acquisition runs in a savepoint and updates the registry row keyed by a hash of the driver name and the physical
 * table identity; the row lock it takes persists after the savepoint is released. Missing keys are registered while
 * holding the {@see BOOTSTRAP} row, so concurrent first writers never race a unique insert.
 */
final class TreeLock
{
    /**
     * Registry key of the row that serializes the registration of missing table keys.
     */
    public const string BOOTSTRAP = '_bootstrap';
    /**
     * Name of the lock registry table, created by the lock migration in the schema of each nested set table.
     */
    public const string TABLE_NAME = 'nested_sets_lock';

    /**
     * Acquires the writer lock of the owner's physical table for the remainder of the active outermost transaction.
     *
     * @param ActiveRecord $owner Record whose physical table is locked.
     * @param int $timeout Maximum wait in seconds for the registry row lock.
     *
     * @throws InvalidConfigException if `$timeout` is outside `1` through `3600`, if no Yii transaction is active on
     * the owner connection, if the table is not a physical base table visible in the catalog, if the lock migration is
     * not applied (missing bootstrap row, or non-InnoDB tables on MySQL), or if the registry row cannot be registered.
     * @throws NotSupportedException if {@see Connection::$enableSavepoint} is `false`, if the driver is not supported,
     * or if a SQLite table is outside the `main` database.
     * @throws Throwable if the lock wait times out or another database error occurs; the savepoint is rolled back.
     */
    public static function acquire(ActiveRecord $owner, int $timeout): void
    {
        $db = $owner::getDb();

        if ($timeout < 1 || $timeout > 3600) {
            throw new InvalidConfigException(
                Message::LOCK_TIMEOUT_OUT_OF_RANGE->getMessage(),
            );
        }

        if ($db->getTransaction() === null || $db->getMasterPdo()->inTransaction() === false) {
            throw new InvalidConfigException(
                Message::TRANSACTION_REQUIRED->getMessage(),
            );
        }

        if ($db->enableSavepoint === false) {
            throw new NotSupportedException(
                Message::LOCK_REQUIRES_SAVEPOINTS->getMessage(),
            );
        }

        $transaction = $db->beginTransaction();

        try {
            [$lockTable, $key] = self::identity($owner, $db);

            $acquire = static function () use ($db, $lockTable, $key, $timeout): int {
                if (self::update($db, $lockTable, $key, $timeout) === 1) {
                    return 1;
                }

                // Serialize only registration of missing table keys; never race an unbounded unique insert.
                if (self::update($db, $lockTable, self::BOOTSTRAP, $timeout) !== 1) {
                    throw new InvalidConfigException(
                        Message::LOCK_BOOTSTRAP_ROW_MISSING->getMessage(),
                    );
                }

                if (self::update($db, $lockTable, $key, $timeout) === 1) {
                    return 1;
                }

                $db
                    ->createCommand()
                    ->insert($lockTable, ['resource' => $key, 'token' => 0])
                    ->execute();

                return self::update($db, $lockTable, $key, $timeout);
            };

            // Oracle applies WAIT to each locking read instead of changing a session setting.
            // identity() has already rejected unsupported drivers.
            $rows = $db->driverName === 'oci' ? $acquire() : match ($db->driverName) {
                'pgsql' => self::postgres($db, $timeout, $acquire),
                'mysql' => self::mysql($db, $timeout, $acquire),
                'sqlsrv' => self::sqlServer($db, $timeout, $acquire),
                'sqlite' => self::sqlite($db, $timeout, $acquire),
            };

            if ($rows !== 1) {
                throw new InvalidConfigException(
                    Message::CANNOT_REGISTER_WRITER_ROW->getMessage(),
                );
            }

            $transaction->commit();
        } catch (Throwable $exception) {
            self::rollBack($db, $transaction, $exception);

            throw $exception;
        }
    }

    /**
     * Returns whether the query matches any row, reading with the same current-read semantics as coordinate reloads.
     *
     * @template T of ActiveRecord
     *
     * @param ActiveQuery<T> $query Query to test; it is cloned and its ordering is dropped.
     *
     * @return bool Whether any row matches the query.
     */
    public static function exists(ActiveQuery $query): bool
    {
        $query = clone $query;

        // Ordering cannot change existence and SQL Server forbids an unbounded ORDER BY in this subquery.
        $query->orderBy(null);

        self::prepareCurrentQuery($query);

        return $query->exists();
    }

    /**
     * Returns the first matching record, reading current MySQL coordinates even under an older repeatable-read view.
     *
     * The query is modified in place: caching is disabled and, on MySQL, the SQL becomes a `FOR UPDATE` locking read.
     *
     * @template T of ActiveRecord
     *
     * @param ActiveQuery<T> $query Query that selects the record.
     *
     * @return T|null Matching record, or `null` if no row matches.
     */
    public static function one(ActiveQuery $query): ActiveRecord|null
    {
        self::prepareCurrentQuery($query);

        return $query->one();
    }

    /**
     * Rolls back the failed scope and clears Yii levels when the server aborted the whole transaction.
     *
     * @param Connection $db Connection that owns the transaction.
     * @param Transaction $transaction Transaction scope to roll back.
     * @param Throwable $cause Failure that triggered the rollback; used to detect SQL Server deadlocks.
     *
     * @throws Throwable if the rollback fails for a reason other than SQLite having already discarded the transaction.
     */
    public static function rollBack(Connection $db, Transaction $transaction, Throwable $cause): void
    {
        if ($db->driverName === 'sqlsrv') {
            self::recoverSqlServer($db, $cause);
        }

        try {
            if ($db->getMasterPdo()->inTransaction() === false) {
                while ($transaction->getIsActive()) {
                    $transaction->rollBack();
                }
            } else {
                $transaction->rollBack();
            }
        } catch (Throwable $failure) {
            if ($db->driverName !== 'sqlite' || self::missingSqliteTransaction($failure) === false) {
                throw $failure;
            }

            $pdo = $db->getMasterPdo();

            try {
                $pdo->rollBack();
            } catch (PDOException $nativeFailure) {
                if (self::missingSqliteTransaction($nativeFailure) === false) {
                    throw $nativeFailure;
                }

                // PDO SQLite on PHP 8.3 caches its transaction flag after an automatic native rollback.
                // Reset the flag with an empty native transaction; preserve the connection and in-memory database.
                $pdo->exec('BEGIN');
                $pdo->rollBack();
            }
            while ($transaction->getIsActive()) {
                $transaction->rollBack();
            }
        }
    }

    /**
     * Resolves the qualified lock registry table and the canonical resource key of the owner's physical table.
     *
     * @return array{string, string} Qualified lock table and canonical resource hash.
     *
     * @throws InvalidConfigException if the table does not exist, is not a visible base table, or, on MySQL, if
     * identifier case rules cannot be resolved or either table does not use InnoDB.
     * @throws NotSupportedException if the driver is not supported or a SQLite table is outside the `main` database.
     */
    private static function identity(ActiveRecord $owner, Connection $db): array
    {
        $schema = $db->getSchema();
        $raw = $schema->getRawTableName($owner::tableName());
        $qualified = $schema->quoteTableName($raw);
        $table = $db->getTableSchema($owner::tableName());

        if ($table === null) {
            throw new InvalidConfigException(
                Message::TABLE_NOT_FOUND->getMessage(),
            );
        }

        $parts = [];
        switch ($db->driverName) {
            case 'pgsql':
                $info = self::info(
                    (new Query())
                        ->select(
                            [
                                'schema_name' => 'n.nspname',
                                'table_id' => new Expression('c.oid::text'),
                            ],
                        )
                        ->from(['c' => 'pg_class'])
                        ->innerJoin(['n' => 'pg_namespace'], 'n.oid = c.relnamespace')
                        ->where(
                            [
                                'and',
                                [
                                    '=',
                                    'c.oid',
                                    new Expression('to_regclass(:name)', [':name' => $qualified]),
                                ],
                                ['c.relkind' => ['r', 'p']],
                            ],
                        )
                        ->one($db),
                );

                $parts[] = $schema->quoteSimpleTableName($info['schema_name']);

                $name = $info['table_id'];
                break;
            case 'mysql':
                $mode = $db
                    ->createCommand('SELECT @@lower_case_table_names')
                    ->queryScalar();

                if (in_array($mode, [0, 1, 2, '0', '1', '2'], true) === false) {
                    throw new InvalidConfigException(
                        Message::CANNOT_RESOLVE_MYSQL_IDENTIFIER_CASE->getMessage(),
                    );
                }

                $condition = $mode === 0 || $mode === '0'
                    ? 'BINARY TABLE_SCHEMA = BINARY COALESCE(:schema, DATABASE()) AND BINARY TABLE_NAME = BINARY :name'
                    : 'LOWER(TABLE_SCHEMA) = LOWER(COALESCE(:schema, DATABASE())) AND LOWER(TABLE_NAME) = LOWER(:name)';

                $info = self::info(
                    (new Query())
                        ->select(
                            [
                                'schema_name' => 'TABLE_SCHEMA',
                                'table_id' => 'TABLE_NAME',
                                'engine' => 'ENGINE',
                            ],
                        )
                        ->from('information_schema.TABLES')
                        ->where(['TABLE_TYPE' => 'BASE TABLE'])
                        ->andWhere(
                            new Expression(
                                $condition,
                                [
                                    ':schema' => $table->schemaName,
                                    ':name' => $table->name,
                                ],
                            ),
                        )
                        ->one($db),
                );

                $engine = (new Query())
                    ->select('ENGINE')
                    ->from('information_schema.TABLES')
                    ->where(
                        [
                            'TABLE_SCHEMA' => $info['schema_name'],
                            'TABLE_NAME' => self::TABLE_NAME,
                        ],
                    )
                    ->scalar($db);

                if ($info['engine'] !== 'InnoDB' || $engine !== 'InnoDB') {
                    throw new InvalidConfigException(
                        Message::LOCK_REQUIRES_INNODB->getMessage(),
                    );
                }

                $parts[] = $schema->quoteSimpleTableName($info['schema_name']);

                $name = $info['table_id'];
                break;
            case 'sqlsrv':
                $catalog = $table instanceof MssqlTableSchema && $table->catalogName !== null && $table->catalogName !== ''
                    ? $table->catalogName
                    : $db->createCommand('SELECT DB_NAME()')->queryScalar();

                if (is_string($catalog) === false) {
                    throw new InvalidConfigException(
                        Message::CANNOT_RESOLVE_SQLSRV_DATABASE->getMessage(),
                    );
                }

                $quotedCatalog = $schema->quoteSimpleTableName($catalog);

                $info = self::info(
                    (new Query())
                        ->select(
                            [
                                'schema_name' => 's.name',
                                'table_id' => 't.object_id',
                            ],
                        )
                        ->from(['t' => $quotedCatalog . '.sys.tables'])
                        ->innerJoin(['s' => "{$quotedCatalog}.sys.schemas"], 's.schema_id = t.schema_id')
                        ->where(
                            [
                                '=',
                                't.object_id',
                                new Expression('OBJECT_ID(:name)', [':name' => $qualified]),
                            ],
                        )
                        ->one($db),
                );

                $parts[] = $quotedCatalog;

                $parts[] = $schema->quoteSimpleTableName($info['schema_name']);

                $name = $info['table_id'];
                break;
            case 'oci':
                $ownerName = $table->schemaName !== null && $table->schemaName !== ''
                    ? $table->schemaName
                    : $db->createCommand("SELECT SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') FROM DUAL")->queryScalar();

                // Oracle catalog identifiers are upper case and Yii quotes every identifier it builds.
                $info = self::info(
                    (new Query())
                        ->select(
                            [
                                'schema_name' => 'OWNER',
                                'table_id' => 'TABLE_NAME',
                            ],
                        )
                        ->from('ALL_TABLES')
                        ->where(
                            [
                                'OWNER' => $ownerName,
                                'TABLE_NAME' => $table->name,
                            ],
                        )
                        ->one($db),
                );

                $parts[] = $schema->quoteSimpleTableName($info['schema_name']);

                $name = $info['table_id'];

                break;
            case 'sqlite':
                if ($table->schemaName !== null && $table->schemaName !== '' && $table->schemaName !== 'main') {
                    throw new NotSupportedException(Message::LOCK_REQUIRES_SQLITE_MAIN_DATABASE->getMessage());
                }

                $info = self::info(
                    (new Query())
                        ->select(['table_id' => 'name'])
                        ->from('main.sqlite_master')
                        ->where(['type' => 'table'])
                        ->andWhere(
                            [
                                '=',
                                'name',
                                new Expression(':name COLLATE NOCASE', [':name' => $table->name]),
                            ],
                        )
                        ->one($db),
                );

                // Only main is supported, and the SQLite schema cannot load metadata for qualified names.
                $name = $info['table_id'];

                break;
            default:
                throw new NotSupportedException(
                    Message::LOCK_DRIVER_NOT_SUPPORTED->getMessage(),
                );
        }

        $parts[] = $schema->quoteSimpleTableName(self::TABLE_NAME);

        return [implode('.', $parts), hash('sha256', $db->driverName . ':' . $name)];
    }

    /**
     * Normalizes a catalog row to lower-case keys and string values.
     *
     * @param array<mixed>|bool $row Catalog row, or `false` when the table is not visible in the catalog.
     *
     * @throws InvalidConfigException if the row is missing, holds non-scalar metadata, or lacks the table identity.
     *
     * @return array{table_id: string, schema_name: string, engine: string} Normalized catalog row with lower-case keys
     * and string values.
     */
    private static function info(array|bool $row): array
    {
        if (is_array($row) === false) {
            throw new InvalidConfigException(
                Message::LOCK_REQUIRES_PHYSICAL_TABLE->getMessage(),
            );
        }

        $result = [];

        foreach ($row as $key => $value) {
            if (is_string($key) === false || (is_string($value) === false && is_int($value) === false)) {
                throw new InvalidConfigException(
                    Message::INVALID_TABLE_METADATA->getMessage(),
                );
            }

            $result[strtolower($key)] = (string) $value;
        }

        if (isset($result['table_id']) === false) {
            throw new InvalidConfigException(
                Message::PHYSICAL_TABLE_IDENTITY_MISSING->getMessage(),
            );
        }

        return [
            'table_id' => $result['table_id'],
            'schema_name' => $result['schema_name'] ?? '',
            'engine' => $result['engine'] ?? '',
        ];
    }

    /**
     * Returns whether the failure reports that SQLite has already discarded the savepoint or the native transaction.
     *
     * @param Throwable $failure The exception to check.
     *
     * @return bool Whether the failure indicates a missing SQLite transaction.
     */
    private static function missingSqliteTransaction(Throwable $failure): bool
    {
        if ($failure instanceof DatabaseException === false && $failure instanceof PDOException === false) {
            return false;
        }
        $message = $failure->errorInfo[2] ?? null;

        return in_array($failure->errorInfo[1] ?? null, [1, '1'], true)
            && is_string($message)
            && (
                str_starts_with($message, 'no such savepoint:')
                || str_contains($message, 'cannot rollback - no transaction is active')
            );
    }

    /**
     * Runs the acquisition with the session `innodb_lock_wait_timeout` set to the timeout, then restores the old value.
     *
     * @param Connection $db Database connection to use.
     * @param callable(): int $acquire Acquisition callback that returns the number of updated registry rows.
     * @param int $timeout Lock wait timeout in seconds.
     *
     * @return int Number of updated registry rows.
     */
    private static function mysql(Connection $db, int $timeout, callable $acquire): int
    {
        $previous = $db
            ->createCommand('SELECT @@SESSION.innodb_lock_wait_timeout')
            ->queryScalar();
        $db
            ->createCommand('SET SESSION innodb_lock_wait_timeout = :timeout', [':timeout' => $timeout])
            ->execute();

        try {
            return $acquire();
        } finally {
            $db
                ->createCommand('SET SESSION innodb_lock_wait_timeout = :timeout')
                ->bindValue(':timeout', $previous, PDO::PARAM_INT)
                ->execute();
        }
    }

    /**
     * Runs the acquisition with the transaction-local `lock_timeout` set to the timeout, then restores the old value.
     *
     * @param Connection $db Database connection to use.
     * @param int $timeout Lock wait timeout in seconds.
     * @param callable(): int $acquire Acquisition callback that returns the number of updated registry rows.
     *
     * @return int Number of updated registry rows.
     */
    private static function postgres(Connection $db, int $timeout, callable $acquire): int
    {
        $previous = $db
            ->createCommand("SELECT current_setting('lock_timeout')")
            ->queryScalar();
        $db
            ->createCommand("SELECT set_config('lock_timeout', :timeout, true)", [':timeout' => $timeout . 's'])
            ->queryScalar();

        // SQL errors roll back this guard's savepoint and its SET LOCAL automatically.
        $rows = $acquire();

        $db
            ->createCommand("SELECT set_config('lock_timeout', :timeout, true)", [':timeout' => $previous])
            ->queryScalar();

        return $rows;
    }

    /**
     * Disables query caching and, on MySQL, turns the query into a `FOR UPDATE` locking read of committed rows.
     *
     * @template T of ActiveRecord
     * @param ActiveQuery<T> $query Query to prepare in place.
     *
     * @throws InvalidConfigException if the query model class is not an {@see ActiveRecord} subclass.
     */
    private static function prepareCurrentQuery(ActiveQuery $query): void
    {
        $query->noCache();

        $modelClass = $query->modelClass;

        if (is_subclass_of($modelClass, ActiveRecord::class) === false) {
            throw new InvalidConfigException(
                Message::MODEL_NOT_ACTIVE_RECORD->getMessage(),
            );
        }

        if ($modelClass::getDb()->driverName === 'mysql') {
            $command = $query->createCommand();
            $query->sql = $command->getSql() . ' FOR UPDATE';
            $query->params = $command->params;
        }
    }

    /**
     * Rolls back the native SQL Server transaction when the server doomed or ended it, or after deadlock `1205`.
     *
     * @param Connection $db Database connection to use.
     * @param Throwable $cause The exception that triggered the recovery.
     */
    private static function recoverSqlServer(Connection $db, Throwable $cause): void
    {
        if ($db->getMasterPdo()->inTransaction() === false) {
            return;
        }

        $state = $db
            ->createCommand('SELECT XACT_STATE()')
            ->queryScalar();

        if (
            in_array($state, [-1, '-1', 0, '0'], true)
            || ($cause instanceof DatabaseException
            && in_array($cause->errorInfo[1] ?? null, [1205, '1205'], true))
        ) {
            // SQLSRV can begin a replacement native transaction while restoring session settings after deadlock 1205.
            $db->getMasterPdo()->rollBack();
        }
    }

    /**
     * Runs the acquisition with the `busy_timeout` pragma set to the timeout, then restores the previous value.
     *
     * @param Connection $db Database connection to use.
     * @param int $timeout The timeout value in seconds.
     * @param callable(): int $acquire Acquisition callback that returns the number of updated registry rows.
     *
     * @throws InvalidConfigException if the current busy timeout cannot be read.
     *
     * @return int The number of updated registry rows.
     */
    private static function sqlite(Connection $db, int $timeout, callable $acquire): int
    {
        $previous = $db
            ->createCommand('PRAGMA busy_timeout')
            ->queryScalar();

        if (is_int($previous) === false && is_numeric($previous) === false) {
            throw new InvalidConfigException(
                Message::CANNOT_READ_SQLITE_BUSY_TIMEOUT->getMessage(),
            );
        }

        $db
            ->createCommand('PRAGMA busy_timeout = ' . ($timeout * 1000))
            ->execute();

        try {
            return $acquire();
        } finally {
            $db
                ->createCommand("PRAGMA busy_timeout = {$previous}")
                ->execute();
        }
    }

    /**
     * Runs the acquisition with the session `LOCK_TIMEOUT` set to the timeout, then restores the previous value.
     *
     * @param Connection $db Database connection to use.
     * @param int $timeout The timeout value in seconds.
     * @param callable(): int $acquire Acquisition callback that returns the number of updated registry rows.
     *
     * @throws InvalidConfigException if the current lock timeout cannot be read.
     *
     * @return int The number of updated registry rows.
     */
    private static function sqlServer(Connection $db, int $timeout, callable $acquire): int
    {
        $previous = $db->createCommand('SELECT @@LOCK_TIMEOUT')->queryScalar();

        if (is_int($previous) === false && is_numeric($previous) === false) {
            throw new InvalidConfigException(
                Message::CANNOT_READ_SQLSRV_LOCK_TIMEOUT->getMessage(),
            );
        }

        // PDO SQLSRV prepares each command in a batch whose SET options are scoped to that batch.
        $db->getMasterPdo()->exec('SET LOCK_TIMEOUT ' . ($timeout * 1000));

        try {
            return $acquire();
        } finally {
            $db->getMasterPdo()->exec('SET LOCK_TIMEOUT ' . (int) $previous);
        }
    }

    /**
     * Flips the token of the registry row identified by `$key`, taking its row lock, and returns the affected rows.
     *
     * @phpstan-impure
     */
    private static function update(Connection $db, string $table, string $key, int $timeout): int
    {
        if ($db->driverName === 'oci') {
            // Oracle WAIT accepts a validated integer literal rather than a bound parameter.
            $db
                ->createCommand(
                    'SELECT [[resource]] FROM ' . $table . ' WHERE [[resource]] = :key FOR UPDATE WAIT ' . $timeout,
                    [':key' => $key],
                )
                ->queryScalar();
        }

        return $db->createCommand()
            ->update($table, ['token' => new Expression('1 - [[token]]')], ['resource' => $key])
            ->execute();
    }
}
