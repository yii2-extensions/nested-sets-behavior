<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\exception;

use function sprintf;

/**
 * Defines nested sets exception message templates.
 *
 * Use {@see Message::getMessage()} to format the template with `sprintf()` arguments.
 */
enum Message: string
{
    /**
     * Error when the behavior is used without an attached owner.
     */
    case BEHAVIOR_NOT_ATTACHED = 'The "owner" property must be set before using the behavior.';

    /**
     * Error when a second root is created while `treeAttribute` is `false`.
     */
    case CANNOT_CREATE_MULTIPLE_ROOTS = "Can not create more than one root when 'treeAttribute' is 'false'.";

    /**
     * Error when a node is created relative to a new record target.
     */
    case CANNOT_CREATE_NODE_TARGET_IS_NEW_RECORD = 'Can not create a node when the target node is new record.';

    /**
     * Error when a sibling of a root node is created.
     */
    case CANNOT_CREATE_NODE_TARGET_IS_ROOT = 'Can not create a node when the target node is root.';

    /**
     * Error when a new record is deleted as a node.
     */
    case CANNOT_DELETE_NEW_RECORD = 'Can not delete a node when it is new record.';

    /**
     * Error when a node is moved as a root while `treeAttribute` is `false`.
     */
    case CANNOT_MOVE_NODE_AS_ROOT = "Can not move a node as the root when 'treeAttribute' is 'false'.";

    /**
     * Error when a node is moved relative to one of its descendants.
     */
    case CANNOT_MOVE_NODE_TARGET_IS_CHILD = 'Can not move a node when the target node is child.';

    /**
     * Error when a node is moved relative to a new record target.
     */
    case CANNOT_MOVE_NODE_TARGET_IS_NEW_RECORD = 'Can not move a node when the target node is new record.';

    /**
     * Error when a node is moved as a sibling of a root node.
     */
    case CANNOT_MOVE_NODE_TARGET_IS_ROOT = 'Can not move a node when the target node is root.';

    /**
     * Error when a node is moved relative to itself.
     */
    case CANNOT_MOVE_NODE_TARGET_IS_SAME = 'Can not move a node when the target node is same.';

    /**
     * Error when a root node is moved as a root.
     */
    case CANNOT_MOVE_ROOT_AS_ROOT = 'Can not move the root node as the root.';

    /**
     * Error when the SQLite busy timeout cannot be read.
     */
    case CANNOT_READ_SQLITE_BUSY_TIMEOUT = 'Cannot read SQLite busy timeout.';

    /**
     * Error when the SQL Server lock timeout cannot be read.
     */
    case CANNOT_READ_SQLSRV_LOCK_TIMEOUT = 'Cannot read SQL Server lock timeout.';

    /**
     * Error when the writer registry row cannot be registered.
     */
    case CANNOT_REGISTER_WRITER_ROW = 'Cannot register the nested set writer row.';

    /**
     * Error when the MySQL identifier case mode cannot be resolved.
     */
    case CANNOT_RESOLVE_MYSQL_IDENTIFIER_CASE = 'Cannot resolve MySQL identifier case rules.';

    /**
     * Error when the SQL Server database cannot be resolved.
     */
    case CANNOT_RESOLVE_SQLSRV_DATABASE = 'Cannot resolve the SQL Server database.';

    /**
     * Error when a root node is deleted outside `deleteWithChildren()` for a specific class.
     */
    case DELETE_ROOT_NOT_SUPPORTED = "Method '%s::delete' is not supported for deleting root nodes.";

    /**
     * Error when a node is inserted without a pending operation for a specific class.
     */
    case INSERT_NOT_SUPPORTED = "Method '%s::insert' is not supported for inserting new nodes.";

    /**
     * Error when the catalog row holds non-scalar table metadata.
     */
    case INVALID_TABLE_METADATA = 'Invalid nested set table metadata.';

    /**
     * Error when the writer lock bootstrap row is missing.
     */
    case LOCK_BOOTSTRAP_ROW_MISSING = "Missing nested set bootstrap row. Apply 'm261016_000001_nested_sets_lock' in the "
    . 'model table schema.';

    /**
     * Error when writer locking is not implemented for the database driver.
     */
    case LOCK_DRIVER_NOT_SUPPORTED = 'Nested set writer locking is not implemented for this database driver.';

    /**
     * Error when a MySQL nested set or registry table does not use InnoDB.
     */
    case LOCK_REQUIRES_INNODB = 'Nested set and nested_sets_lock tables must use InnoDB; apply the lock migration '
    . 'first.';

    /**
     * Error when the nested set table is not a physical base table visible in the catalog.
     */
    case LOCK_REQUIRES_PHYSICAL_TABLE = 'Nested set locking requires a physical base table visible in the database '
    . 'catalog.';

    /**
     * Error when savepoints are disabled on the connection.
     */
    case LOCK_REQUIRES_SAVEPOINTS = 'Nested set writer locking requires savepoint support; enable '
    . '\'Connection::$enableSavepoint\'.';

    /**
     * Error when a SQLite table is outside the `main` database.
     */
    case LOCK_REQUIRES_SQLITE_MAIN_DATABASE = 'Nested set locking supports the SQLite main database only.';

    /**
     * Error when the lock timeout is outside the accepted range.
     */
    case LOCK_TIMEOUT_OUT_OF_RANGE = "Nested set lockTimeout must be between '1' and '3600' seconds.";

    /**
     * Error when a nested set query model is not an ActiveRecord class.
     */
    case MODEL_NOT_ACTIVE_RECORD = 'Nested set queries require an ActiveRecord model.';

    /**
     * Error when a node optimistic lock version has changed.
     */
    case NODE_MODIFIED = 'The nested set node has been modified by another request.';

    /**
     * Error when a node no longer exists in storage.
     */
    case NODE_NOT_FOUND = 'The nested set node no longer exists.';

    /**
     * Error when the catalog row lacks the physical table identity.
     */
    case PHYSICAL_TABLE_IDENTITY_MISSING = 'Missing physical table identity.';

    /**
     * Error when the owner model has no primary key for a specific class.
     */
    case PRIMARY_KEY_REQUIRED = "'%s' must have a primary key.";

    /**
     * Error when the nested set table does not exist.
     */
    case TABLE_NOT_FOUND = 'The nested set table does not exist.';

    /**
     * Error when the target node uses another database connection or table.
     */
    case TARGET_STORAGE_MISMATCH = 'The target node must use the same database connection and table.';

    /**
     * Error when a write runs without an active Yii database transaction.
     */
    case TRANSACTION_REQUIRED = "Nested set writes require a Yii database transaction. Configure 'transactions()' or "
    . 'begin an outer transaction.';

    /**
     * Error when a subtree is moved between trees while `treeAttribute` is `false`.
     */
    case TREE_ATTRIBUTE_REQUIRED = "Moving a subtree between trees requires 'treeAttribute'.";

    /**
     * Error when the pending operation does not move a node relative to a target.
     */
    case UNSUPPORTED_OPERATION = "Unsupported operation: '%s'";

    /**
     * Returns the formatted message string for the error case.
     *
     * @param int|string ...$argument Values to insert into the message template.
     *
     * @return string Formatted error message with interpolated arguments.
     */
    public function getMessage(int|string ...$argument): string
    {
        return sprintf($this->value, ...$argument);
    }
}
