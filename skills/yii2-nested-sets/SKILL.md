---
name: yii2-nested-sets
description: Configure, query, migrate, or troubleshoot hierarchical Yii ActiveRecord models using yii2-extensions/nested-sets-behavior, including structural writes and writer locking.
license: BSD-3-Clause
---

# Nested sets for Yii ActiveRecord

Use `yii2\extensions\nestedsets\NestedSetsBehavior` for tree operations and `NestedSetsQueryBehavior` on the model's
ActiveQuery for `roots()` and `leaves()`. Preserve the application's table, scenarios, validation and transaction
boundaries. This skill describes the 0.2 API with PHP `>=8.3`, PDO, and Yii `~2.0.55 || ^22.0@dev`; earlier versions
have different transaction behavior and lack the writer-lock API. Determine the actual implementation before applying
these contracts.

## Resolve the implementation and relevant documentation

For a root package checkout, `composer show --self --format=json` identifies package metadata. For a dependency, resolve
the configured vendor directory and the package's real path using `composer/installed.php`:
`versions[package].install_path`, `pretty_version`, and Composer's selected `reference`, or equivalent Composer APIs.
Compare `composer.lock` without treating it as proof of installed files. Resolve `yiisoft/yii2` separately.

Inspect the current `HEAD` and files of every live checkout, including a symlinked path installation even when clean.
Files govern when Composer metadata differs; uncommitted changes have no matching source SHA. Do not assume that a path
reference is a Git commit, prefer source over dist, or invent a tag from a version constraint or branch alias. Use a
selected reference or matching tag only when its source provenance is established. If only the lock file exists, report
a lock-only resolution and avoid claims about an installed API.

Use the affected guides and source at that revision. In a package checkout, the relevant routes are:

| Task                                                                    | Local source                                                                        |
| ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| Install, choose a Yii constraint, create tables                         | `composer.json`, `docs/installation.md`, `README.md`, `migrations/`                 |
| Map attributes, enable multiple trees, configure transactions and locks | `docs/configuration.md`, `src/NestedSetsBehavior.php`, `src/TreeLock.php`           |
| Build, query, move or delete a tree                                     | `docs/examples.md`, `src/NestedSetsBehavior.php`, `src/NestedSetsQueryBehavior.php` |
| Upgrade or convert adjacency lists                                      | `UPGRADE.md`, `docs/configuration.md`                                               |
| Check invariants and database-specific behavior                         | `docs/testing.md`, available integrity/concurrency tests                            |

A copied skill does not imply a neighboring package or `../../docs`. Distribution archives can omit `docs/`. If local
guides are absent, use matching files from <https://github.com/yii2-extensions/nested-sets-behavior> only when network
access is allowed. For hosted Yii documentation, start at <https://yii2-framework.github.io/docs/llms.txt>; append
`index.md` to a page URL for Markdown. Verify source revisions before using hosted pages or API catalogs for either
package. When matching docs cannot be retrieved, inspect local source/PHPDoc and changelog, state the gap, and retain
the inspected API instead of substituting newer documentation. This skill works without private evaluation tools.

## Apply the inspected contracts

- Match `tableName()` and behavior attributes to the actual schema. `treeAttribute` defaults to `false`: one root per
  table. Enable it for multiple independent trees; its value identifies the root. Let structural methods maintain `lft`,
  `rgt`, `depth`, and the tree column; validate application fields without mass assigning structural fields.
- Insert with `makeRoot()`, `appendTo()`, `prependTo()`, `insertBefore()`, or `insertAfter()`. Plain `save()` is for
  ordinary attributes on an existing node. A move target must be persisted and use the same `Connection` instance and
  `tableName()` result as the owner. It cannot be the owner or its descendant, and sibling insertion cannot target a
  root. Moving an existing node to a new root needs multiple-tree mode.
- Structural methods open a transaction or nested savepoint, roll back `false` or exceptions, and clear the pending
  operation. Inspect `false` and validation errors; retry the structural method after correction, not plain `save()`.
  Ordinary `delete()` needs `OP_DELETE`/`OP_ALL` in each writable scenario or an explicit Yii transaction.
  `deleteWithChildren()` returns the total deleted rows including the owner, or `false` if cancelled; descendant hooks
  do not run. Use it for roots too, since ordinary `delete()` rejects roots. Keep required subtree cleanup in the same
  transaction.
- Before using the 0.2 writer lock, apply `m261016_000001_nested_sets_lock.php` in the nested set table's schema. The
  registry is unprefixed, needs its `_bootstrap` row, and is never created by runtime operations. Connections need
  `SELECT`, `INSERT`, `UPDATE` and catalog visibility. Keep savepoints enabled, use Yii transaction APIs, and use InnoDB
  for both MySQL tables. Views, synonyms and SQLite attached databases are unsupported.
- The lock serializes cooperating writers of a physical table, including different trees in that table. Successful
  nested calls retain it until the outer transaction ends; rollback to an earlier savepoint can release locks acquired
  later, so reacquire before further application reads/writes. SQLite still serializes writers across its database file.
  Separate lock rows do not imply independent writes on every driver.
- For a read that decides a write, or raw SQL/`updateAll()`/`deleteAll()`, begin the transaction and call
  `(new Model())->acquireTreeLock()` before reading. Bypass query caching for those decision reads. The lock does not
  repair invalid boundary updates or protect noncooperating writers. Stop old-version writers during rollout. Keep a
  consistent lock order across tables. `lockTimeout` is `1..3600` seconds, default `10`, per registry-lock wait; it is
  not a whole-operation deadline.
- Treat database deadlocks, lock timeouts and serialization conflicts as failed units of work. Roll back the complete
  outer transaction and reload models before a bounded retry. Do not commit a server-aborted transaction. A lock does
  not refresh an already established application snapshot. Refresh surviving records after rollback and recreate records
  whose insertion was rolled back; refresh other loaded instances before using cached coordinates for reads.
- `children()` means all descendants, `children(1)` direct children; `parents(1)` selects the direct parent. A depth
  argument limits relative depth, not an absolute level. Tree queries do not by themselves lock application decisions.
  For `children()->exists()`, remove ordering with `orderBy(null)` for SQL Server compatibility. Use
  `orderBy(['depth' => SORT_DESC])` to select the deepest common ancestor, rather than comparing primary keys.

## Evidence for the requested change

Check the schema, behavior configuration, and affected PHP examples against the actual source. For structural changes,
verify root count, unique contiguous boundaries within each tree, strict containment, and depth on a disposable fixture.
Concurrent-write claims need independent connections/processes on the relevant driver and isolation mode, including the
claimed commit/rollback or failure path. A sequential or SQLite-only pass cannot establish another driver's concurrency
behavior. Use existing checks where suitable; do not install dependencies or start services without the task's
authorization. Report files, resolved versions/references, actual checks and skips or limitations.
