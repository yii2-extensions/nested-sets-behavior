# Testing

This package provides a consistent set of [Composer](https://getcomposer.org/) scripts for local validation.

Tool references:

- [Actionlint](https://github.com/rhysd/actionlint) for GitHub Actions workflow linting.
- [Codespell](https://github.com/codespell-project/codespell) for spelling checks.
- [Composer Require Checker](https://github.com/maglnet/ComposerRequireChecker) for dependency definition checks.
- [Easy Coding Standard (ECS)](https://github.com/easy-coding-standard/easy-coding-standard) for coding standards.
- [EditorConfig Checker](https://github.com/editorconfig-checker/editorconfig-checker) for EditorConfig compliance.
- [Gitleaks](https://github.com/gitleaks/gitleaks) for secret scanning.
- [Infection](https://infection.github.io/) for mutation testing.
- [Markdownlint-cli2](https://github.com/DavidAnson/markdownlint-cli2) for Markdown linting.
- [PHPStan](https://phpstan.org/) for static analysis.
- [PHPUnit](https://phpunit.de/) for unit tests.
- [Prettier](https://prettier.io/) for Markdown and YAML formatting checks.
- [Rector](https://github.com/rectorphp/rector) for automated refactoring.
- [Yamllint](https://github.com/adrienverge/yamllint) for YAML linting.
- [Zizmor](https://github.com/zizmorcore/zizmor) for GitHub Actions security audits.

## Automated refactoring (Rector)

Run Rector to apply automated code refactoring. This command modifies files.

```bash
composer rector
```

Preview the refactoring without modifying files.

```bash
composer rector-check
```

## Coding standards (ECS)

Check coding standards without modifying files.

```bash
composer ecs-check
```

Apply coding standard fixes. Review the changes before keeping them.

```bash
composer ecs
```

## Dependency definition check

Verify that runtime dependencies are correctly declared in `composer.json`.

```bash
composer check-dependencies
```

## Mutation testing (Infection)

Run mutation testing.

```bash
composer mutation
```

Run mutation testing with PHPStan static analysis enabled.

```bash
composer mutation-static
```

Thresholds, thread count, timeout and memory options live in `infection.json5`. CI enforces the same thresholds.

The mutation campaign runs the `sqlite` and `mutation` PHPUnit groups, selected by `testFrameworkExtraArgs` in
`infection.json5`. The `mutation` group holds the native MySQL and PostgreSQL writer regressions, so both servers must
be reachable. Infection runs four workers, and every worker needs its own disposable database. Set a DSN such as
`NESTED_SETS_TEST_MYSQL_DSN='mysql:host=127.0.0.1;dbname=yiitest_{token}'` and pre-create `yiitest_initial` plus
`yiitest_1` through `yiitest_4`. The fixture configuration expands `{token}` from Infection's `TEST_TOKEN`, using
`initial` for the initial suite. Literal DSNs remain unchanged. The mutation workflow provisions this pool, and no tests
are removed from the `sqlite,mutation` groups. The 120-second timeout accommodates the native database tests. Keep the
thread count at four or provision one database per additional worker.

Check the report for skipped mutants as well as survivors: a 100% score with skipped mutants does not verify the
complete campaign.

## Repository quality checks

Make the CLI tools used by the shared quality workflow available on `PATH`: actionlint with its ShellCheck helper,
codespell, EditorConfig Checker, markdownlint-cli2, Prettier and yamllint. Run all checks:

```bash
composer quality
```

Each tool also has its own script:

```bash
composer actionlint
composer codespell
composer editorconfig-checker
composer markdownlint
composer prettier
composer yamllint
```

The linter configuration lives in `.github/linters`, and the Prettier configuration in `.prettierrc.json` and
`.prettierignore`. CI uses the same files.

## Static analysis (PHPStan)

Run static analysis.

```bash
composer static
```

## Unit tests (PHPUnit)

Run the SQLite test suite. It needs no database server.

```bash
composer tests
```

The external database suites are described in [Database testing](#database-testing).

## Workflow security checks

Make zizmor and Gitleaks available on `PATH`. Audit the current workflow files with the same pedantic persona used by
the shared security workflow:

```bash
composer zizmor
```

Scan Git history for secrets with the repository's Gitleaks configuration:

```bash
composer gitleaks
```

Run both checks. Findings make the command fail; inspect them rather than disabling the checks.

```bash
composer security
```

Zizmor needs GitHub API access for its online audits. If the tool runs offline, report that limit separately. To request
an offline audit explicitly:

```bash
composer zizmor -- --offline
```

Report hosted CI execution and its PHP/OS matrix separately from these local commands. Keep generated reports in ignored
`internal/`.

## Passing extra arguments

Composer scripts support forwarding additional arguments using `--`.

Run PHPUnit with code coverage report generation.

```bash
composer tests -- --coverage-html internal/coverage
```

Run PHPStan with a different memory limit.

```bash
composer static -- --memory-limit=512M
```

## Database testing

This package supports testing with multiple database systems to ensure compatibility across different environments.

- **MySQL** (8.0, 8.4, latest)
- **Oracle** (23)
- **PostgreSQL** (15, 16, 17)
- **SQL Server** (2022-latest)
- **SQLite** (default, in-memory) — No setup required

The external database suites drop and recreate `migration`, `tree`, `multiple_tree`, and `nested_sets_lock`. Use a
disposable database with no application data. Override the fixture connection with `NESTED_SETS_TEST_<DRIVER>_DSN`,
`NESTED_SETS_TEST_<DRIVER>_USERNAME`, and `NESTED_SETS_TEST_<DRIVER>_PASSWORD`. Driver names are `MYSQL`, `PGSQL`,
`MSSQL`, `ORACLE`, and `SQLITE`. For example, for a disposable PostgreSQL container published on port 15432:

```bash
NESTED_SETS_TEST_PGSQL_DSN='pgsql:host=127.0.0.1;port=15432;dbname=yiitest' \
    composer exec phpunit -- --group=pgsql
```

The concurrency suites start independent PHP processes with `proc_open` and use bounded filesystem gates for test
coordination. SQLite concurrency tests use a temporary database file and remove it afterward. The SQL Server snapshot
test enables `ALLOW_SNAPSHOT_ISOLATION` only on the disposable fixture database. Do not run separate suites against the
same fixture database concurrently. The suites exercise commit/rollback, savepoints, stale snapshots, first-row
registration, aliases and prefixes, process exit, timeouts, deadlocks, cross-tree moves and both deletion paths, with
driver-specific coverage and skips. SQLite skips the independent-table writer test because it locks the database file. A
SQLite-only pass does not establish the behavior of another driver; record the driver, version and skipped cases when
reporting concurrency results.

### Database-specific testing

Run tests against a specific database system using PHPUnit groups. The `tests` script is fixed to the `sqlite` group, so
the other groups run through `composer exec`.

```bash
# MySQL
composer exec phpunit -- --group=mysql

# Oracle
composer exec phpunit -- --group=oci

# PostgreSQL
composer exec phpunit -- --group=pgsql

# SQL Server
composer exec phpunit -- --group=mssql

# SQLite (default - in-memory database)
composer tests
```

CI runs each database group in its own `build-<database>` workflow, using the same version matrices as the list above.

### Local development setup

For local testing with real databases, you can use Docker.

#### MySQL

```bash
docker run -d --name mysql-test \
    -e MYSQL_ROOT_PASSWORD=root \
    -e MYSQL_DATABASE=yiitest \
    -p 3306:3306 \
    mysql:8.4

# Configure your database connection and run.
composer exec phpunit -- --group=mysql
```

#### Oracle

```bash
docker run -d --name oracle-test \
    -e ORACLE_PASSWORD=root \
    -e ORACLE_DATABASE=yiitest \
    -p 1521:1521 \
    gvenzl/oracle-free:23

# Configure your database connection and run.
composer exec phpunit -- --group=oci
```

#### PostgreSQL

```bash
docker run -d --name pgsql-test \
    -e POSTGRES_PASSWORD=root \
    -e POSTGRES_DB=yiitest \
    -e POSTGRES_USER=root \
    -p 5432:5432 \
    postgres:17

# Configure your database connection and run.
composer exec phpunit -- --group=pgsql
```

#### SQL Server

```bash
docker run -d --name mssql-test \
    -e ACCEPT_EULA=Y \
    -e 'SA_PASSWORD=YourStrong!Passw0rd' \
    -e MSSQL_PID=Developer \
    -p 1433:1433 \
    mcr.microsoft.com/mssql/server:2022-latest

# Create test database.
docker exec -it mssql-test /opt/mssql-tools18/bin/sqlcmd \
    -C -S localhost -U SA -P 'YourStrong!Passw0rd' \
    -Q "CREATE DATABASE yiitest;"

# Configure your database connection and run.
composer exec phpunit -- --group=mssql
```

#### SQLite

```bash
composer tests
```

## Documentation and skills

Use the repository's `.prettierrc.json`, `.editorconfig`, and `.github/linters/.markdown-lint.yml` with locally
available Prettier, EditorConfig Checker, and Markdownlint (`composer quality` runs them). Check relative links and
anchors, lint changed PHP examples, and exercise examples that make data or concurrency claims against disposable
fixtures. Skill installation, client discovery, activation, and successful task behavior are separate checks; record
only those actually performed. Keep private evaluation scripts, fixtures, and results under ignored `internal/`, outside
public skills and archives.

[Back to documentation](../README.md#documentation)
