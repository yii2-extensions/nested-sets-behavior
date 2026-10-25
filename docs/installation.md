# Installation guide

## Requirements

- [PHP](https://www.php.net/downloads) 8.3 or later.
- [Yii2](https://github.com/yiisoft/yii2) matching `~2.0.55 || ^22.0@dev`.
- PHP PDO and the PDO driver for the selected database.
- [Composer](https://getcomposer.org/download/) for dependency management.

## Install the extension

Select the `0.2` API described by these guides:

```bash
composer require yii2-extensions/nested-sets-behavior:^0.2
```

### Yii 2.0

Yii 2.0 uses Bower asset packages. If the application does not already configure a source for them, add Asset Packagist
before installing:

```bash
composer config repositories.asset-packagist composer https://asset-packagist.org
composer require yii2-extensions/nested-sets-behavior:^0.2 'yiisoft/yii2:~2.0.55'
```

### Yii 22

Use the declared Yii 22 constraint:

```bash
composer require yii2-extensions/nested-sets-behavior:^0.2 'yiisoft/yii2:^22.0@dev'
```

After resolving dependencies, verify the runtime with `composer check-platform-reqs`. Before writing trees, apply the
lock registry migration and configure the model as described below.

## Next steps

- [Create the database tables and configure a model](../README.md#database-setup).
- [Configure transactions and writer locking](configuration.md#transaction-configuration).
- [Build and query trees](examples.md).
- [Run the test suites](testing.md).

[Back to documentation](../README.md#documentation)
