# Testing

## Checking dependencies

This package uses [composer-require-checker](https://github.com/maglnet/ComposerRequireChecker) to check if all dependencies are correctly defined in `composer.json`.

To run the checker, execute the following command.

```shell
composer run check-dependencies
```

## Easy coding standard

The code is checked with [Easy Coding Standard](https://github.com/easy-coding-standard/easy-coding-standard) and
[PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer). To run it.

```shell
composer run ecs
```

## Mutation testing

Mutation testing is checked with [Infection](https://infection.github.io/). To run it.

```shell
composer run mutation
```

With PHPStan analysis, it will also check for static analysis issues during mutation testing.

```shell
composer run mutation-static
```

## Static analysis

The code is statically analyzed with [PHPStan](https://phpstan.org/). To run static analysis.

```shell
composer run static
```

## Unit Tests

The code is tested with [PHPUnit](https://phpunit.de/). To run tests.

```shell
composer run test
```

### Database Testing

This package supports testing with multiple database systems to ensure compatibility across different environments.

- **MySQL** (8.0, 8.4, latest).
- **Oracle** (23).
- **PostgreSQL** (15, 16, 17) .
- **SQL Server** (2022-latest).
- **SQLite** (default, in-memory) - No setup required.

#### Database-Specific Testing

Run tests against specific database systems using PHPUnit groups.

```shell
# MySQL
./vendor/bin/phpunit --group mysql

# Oracle
./vendor/bin/phpunit --group oci

# PostgreSQL  
./vendor/bin/phpunit --group pgsql

# SQL Server
./vendor/bin/phpunit --group mssql

# SQLite (default - in-memory database)
./vendor/bin/phpunit --group sqlite
```

#### Local Development Setup

For local testing with real databases, you can use Docker.

##### MySQL
```shell
docker run -d --name mysql-test \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=yiitest \
  -p 3306:3306 \
  mysql:8.4

# Configure your database connection and run.
./vendor/bin/phpunit --group mysql
```

##### Oracle
```shell
docker run -d --name oracle-test \
  -e ORACLE_PASSWORD=root \
  -e ORACLE_DATABASE=yiitest \
  -p 1521:1521 \
  gvenzl/oracle-free:23

# Configure your database connection and run.
./vendor/bin/phpunit --group oci
```

##### PostgreSQL
```shell
docker run -d --name pgsql-test \
  -e POSTGRES_PASSWORD=root \
  -e POSTGRES_DB=yiitest \
  -e POSTGRES_USER=root \
  -p 5432:5432 \
  postgres:17

# Configure your database connection and run.
./vendor/bin/phpunit --group pgsql
```

##### SQL Server
```shell
docker run -d --name mssql-test \
  -e ACCEPT_EULA=Y \
  -e SA_PASSWORD=YourStrong!Passw0rd \
  -e MSSQL_PID=Developer \
  -p 1433:1433 \
  mcr.microsoft.com/mssql/server:2022-latest

# Create test database.
docker exec -it mssql-test /opt/mssql-tools18/bin/sqlcmd \
  -C -S localhost -U SA -P 'YourStrong!Passw0rd' \
  -Q "CREATE DATABASE yiitest;"

# Configure your database connection and run.
./vendor/bin/phpunit --group mssql
```

##### SQLite
SQLite does not require any setup. It uses an in-memory database by default. You can run tests directly.

```shell
./vendor/bin/phpunit --group sqlite
```
