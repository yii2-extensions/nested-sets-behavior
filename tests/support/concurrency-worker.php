<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

use RuntimeException;
use Throwable;
use yii\console\Application;
use yii\db\{ActiveRecord, Connection, Transaction};
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{ConcurrencyTree, MultipleTree, Tree};

$bootstrap = $argv[1] ?? dirname(__DIR__) . '/bootstrap.php';

require $bootstrap;

/**
 * @param array<string, mixed> $message
 */
function emitMessage(array $message): void
{
    echo json_encode($message, JSON_THROW_ON_ERROR) . "\n";

    flush();
}

/**
 * @param array<array-key, mixed> $data
 */
function stringOption(array $data, string $key, string $default = ''): string
{
    $value = $data[$key] ?? $default;

    if (is_string($value) === false) {
        throw new RuntimeException('Invalid string option: ' . $key);
    }

    return $value;
}

/**
 * @param array<array-key, mixed> $data
 *
 * @return array<string, mixed>
 */
function arrayOption(array $data, string $key): array
{
    $value = $data[$key] ?? [];

    if (is_array($value) === false) {
        throw new RuntimeException('Invalid array option: ' . $key);
    }

    $result = [];

    foreach ($value as $name => $item) {
        if (is_string($name) === false) {
            throw new RuntimeException('Invalid option key.');
        }

        $result[$name] = $item;
    }

    return $result;
}

try {
    $input = stream_get_contents(STDIN);

    if ($input === false) {
        throw new RuntimeException('Cannot read worker input.');
    }

    $data = json_decode($input, true, flags: JSON_THROW_ON_ERROR);

    if (is_array($data) === false) {
        throw new RuntimeException('Worker input must be an object.');
    }

    new Application(
        [
            'id' => 'concurrency-worker',
            'basePath' => dirname(__DIR__, 2),
            'components' => ['db' => arrayOption($data, 'db')],
        ],
    );

    $db = \Yii::$app->getDb();

    $db->tablePrefix = stringOption($data, 'prefix');

    $isolation = stringOption($data, 'isolation');
    $outer = null;

    if (($data['outer'] ?? false) === true) {
        $before = $db->driverName !== 'pgsql' && $db->driverName !== 'oci' && $isolation !== '' ? $isolation : null;
        $outer = $db->beginTransaction($before);

        if ($before === null && $isolation !== '') {
            $outer->setIsolationLevel($isolation);
        }
    }

    $class = stringOption($data, 'model') === 'multiple' ? MultipleTree::class : Tree::class;
    $table = stringOption($data, 'table');

    if ($table !== '') {
        ConcurrencyTree::$table = $table;

        $class = ConcurrencyTree::class;
    }

    if (($data['warmSnapshot'] ?? false) === true) {
        $class::find()->count();

        emitMessage(['phase' => 'snapshot']);
    }

    $gate = stringOption($data, 'gate');
    $wait = static function () use ($gate): void {
        $deadline = microtime(true) + 20;

        while (is_file($gate) === false) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Worker gate timed out.');
            }

            usleep(10000);
        }
    };
    $source = arrayOption($data, 'source');
    $node = new $class(['name' => stringOption($data, 'name', 'worker')]);

    if ($source !== []) {
        $node->setAttributes($source, false);
        $node->setOldAttributes($source);
    }

    $target = new $class();
    $targetAttributes = arrayOption($data, 'target');

    if ($targetAttributes !== []) {
        $target->setAttributes($targetAttributes, false);
        $target->setOldAttributes($targetAttributes);
    }

    $behavior = $node->getBehavior('nestedSetsBehavior');

    if ($behavior instanceof NestedSetsBehavior === false) {
        throw new RuntimeException('Missing nested sets behavior.');
    }

    if ($behavior->canSetProperty('lockTimeout')) {
        $seconds = $data['lockSeconds'] ?? 1;

        if (is_int($seconds) === false) {
            throw new RuntimeException('Invalid lock duration.');
        }

        $behavior->lockTimeout = $seconds;
    }

    $class::getTableSchema();

    if (stringOption($data, 'operation') === 'lock-two') {
        Tree::getTableSchema();
        MultipleTree::getTableSchema();
    }

    $timeoutSql = match ($db->driverName) {
        'pgsql' => "SELECT current_setting('lock_timeout')",
        'mysql' => 'SELECT @@SESSION.innodb_lock_wait_timeout',
        'sqlsrv' => 'SELECT @@LOCK_TIMEOUT',
        'sqlite' => 'PRAGMA busy_timeout',
        default => null,
    };

    if (array_key_exists('timeoutSetting', $data)) {
        $setting = $data['timeoutSetting'];

        if (is_int($setting) === false) {
            throw new RuntimeException('Invalid timeout setting.');
        }

        match ($db->driverName) {
            'pgsql' => $db
                ->createCommand("SELECT set_config('lock_timeout', :value, false)", [':value' => $setting . 'ms'])
                ->queryScalar(),
            'mysql' => $db
                ->createCommand('SET SESSION innodb_lock_wait_timeout = :value', [':value' => $setting])
                ->execute(),
            'sqlsrv' => $db->getMasterPdo()->exec('SET LOCK_TIMEOUT ' . $setting),
            'sqlite' => $db->createCommand('PRAGMA busy_timeout = ' . $setting)->execute(),
            default => null,
        };
    }

    $timeoutBefore = $timeoutSql !== null ? $db->createCommand($timeoutSql)->queryScalar() : null;

    emitMessage(['phase' => 'prepared']);

    if (($data['waitBefore'] ?? false) === true) {
        $wait();
    }

    $hold = stringOption($data, 'hold');

    if ($hold === 'before-insert') {
        $node->on(
            ActiveRecord::EVENT_BEFORE_INSERT,
            static function () use ($wait): void {
                emitMessage(['phase' => 'held']);

                $wait();
            },
        );
    }

    if (($data['failAfter'] ?? false) === true) {
        $node->on(
            ActiveRecord::EVENT_AFTER_INSERT,
            static function (): never {
                throw new RuntimeException('Injected worker failure.');
            },
        );
    }

    if (($data['reenter'] ?? false) === true) {
        $node->on(
            ActiveRecord::EVENT_AFTER_INSERT,
            static function () use ($class, $node): void {
                $child = new $class(['name' => 'nested']);
                $behavior = $child->getBehavior('nestedSetsBehavior');

                if ($behavior instanceof NestedSetsBehavior === false || $behavior->appendTo($node) === false) {
                    throw new RuntimeException('Reentrant write failed.');
                }
            },
        );
    }

    $operation = stringOption($data, 'operation', 'root');

    if ($operation === 'lock-two') {
        $behavior->acquireTreeLock();

        emitMessage(['phase' => 'held']);

        $wait();

        $otherClass = $class === Tree::class ? MultipleTree::class : Tree::class;
        $other = (new $otherClass())->getBehavior('nestedSetsBehavior');

        if ($other instanceof NestedSetsBehavior === false) {
            throw new RuntimeException('Missing second writer behavior.');
        }

        $other->lockTimeout = $behavior->lockTimeout;

        $other->acquireTreeLock();
    }

    $result = match ($operation) {
        'lock-two' => true,
        'root' => $behavior->makeRoot(),
        'append' => $behavior->appendTo($target),
        'prepend' => $behavior->prependTo($target),
        'delete' => $node->delete(),
        'subtree' => $behavior->deleteWithChildren(),
        default => throw new RuntimeException('Unknown worker operation.'),
    };

    if ($hold === 'after-method') {
        emitMessage(['phase' => 'held']);

        $wait();
    }

    if ($outer !== null) {
        if (($data['rollback'] ?? false) === true) {
            $outer->rollBack();
        } else {
            $outer->commit();
        }
    }

    emitMessage(
        [
            'phase' => 'finished',
            'success' => $result !== false,
            'attributes' => $node->getAttributes(),
            'timeoutBefore' => $timeoutBefore,
            'timeoutAfter' => $timeoutSql !== null ? $db->createCommand($timeoutSql)->queryScalar() : null,
        ],
    );
} catch (Throwable $exception) {
    $timeoutAfter = null;
    $transactionActive = false;
    $recovered = false;
    $nativeActive = false;

    if (isset($db) && $db instanceof Connection) {
        $transactionActive = $db->getTransaction() !== null;
        $nativeActive = $db->getMasterPdo()->inTransaction();

        if (isset($timeoutSql) && is_string($timeoutSql)) {
            try {
                $timeoutAfter = $db->createCommand($timeoutSql)->queryScalar();
            } catch (Throwable $readFailure) {
                $timeoutAfter = $readFailure::class;
            }
        }
    }

    if (
        isset($data, $db, $outer)
        && is_array($data)
        && ($data['recover'] ?? false) === true
        && $outer instanceof Transaction
        && $db instanceof Connection
        && $outer->getIsActive()
    ) {
        $recovered = in_array(
            $db->createCommand('SELECT 1' . ($db->driverName === 'oci' ? ' FROM DUAL' : ''))->queryScalar(),
            [1, '1'],
            true,
        );

        $outer->rollBack();
    }

    emitMessage(
        [
            'phase' => 'finished',
            'success' => false,
            'recovered' => $recovered,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'timeoutBefore' => $timeoutBefore ?? null,
            'timeoutAfter' => $timeoutAfter,
            'transactionActive' => $transactionActive,
            'nativeActive' => $nativeActive,
        ],
    );

    exit(1);
}
