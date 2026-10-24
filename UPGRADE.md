# Upgrade Guide

## 0.2.0

- Upgrade PHP to 8.3 or later and Yii to `~2.0.55 || ^22.0@dev`.
- Create the `nested_sets_lock` table before the first structural write: stop all writers, copy
  `migrations/m261016_000001_nested_sets_lock.php` into the application migrations, apply it with `./yii migrate/up` in
  every schema that contains a nested set table, grant `SELECT`, `INSERT` and `UPDATE` on it plus read access to the
  catalog views, then deploy and restart the writers. The table is unprefixed; qualify it in the copied migration for
  other schemas. Do not run earlier releases against the same tables.
- Ordinary `delete()` requires a transaction: keep `transactions()` returning `OP_ALL` (or `OP_DELETE`) or begin one
  explicitly, otherwise it raises `yii\base\InvalidConfigException`. Keep savepoints enabled for structural methods and
  `deleteWithChildren()`, and after an outer rollback refresh surviving records and recreate records inserted in it.
- On MySQL, use InnoDB for the model and lock tables. Views, synonyms and SQLite attached databases are not supported.
- Handle `yii\db\StaleObjectException` for deleted owners or targets and changed optimistic locks by reloading the
  records before retrying. Targets must use the owner's connection and table.
- A failed structural call clears its pending operation; call the structural method again instead of a later plain
  `save()`.
- Code that changes the tree with raw SQL, `updateAll()` or `deleteAll()` must call `acquireTreeLock()` in the same
  transaction first and retry the whole transaction on lock, deadlock or serialization errors. Adjust `lockTimeout`
  (default `10` seconds) if needed.
