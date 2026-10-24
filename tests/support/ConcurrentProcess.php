<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

use RuntimeException;

/**
 * Runs a database writer in a separate PHP process with bounded pipe waits.
 */
final class ConcurrentProcess
{
    private string $buffer = '';

    private bool $closed = false;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $messages = [];

    /**
     * @var array{1: resource, 2: resource}
     */
    private array $pipes;

    /**
     * @var resource
     */
    private mixed $process;

    /**
     * @param array<string, mixed> $payload Worker options encoded as JSON on the worker's standard input.
     */
    public function __construct(array $payload)
    {
        $pipes = [];

        // Child writers must load the same bootstrap, including Infection's interceptor when present.
        $bootstrap = __DIR__ . '/../bootstrap.php';

        foreach (get_included_files() as $included) {
            if (str_contains($included, '/interceptor.autoload.') && str_ends_with($included, '.infection.php')) {
                $bootstrap = $included;

                break;
            }
        }

        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/concurrency-worker.php', $bootstrap],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );

        if (is_resource($process) === false || isset($pipes[0], $pipes[1], $pipes[2]) === false) {
            throw new RuntimeException('Cannot start concurrency worker.');
        }

        $this->process = $process;
        $this->pipes = [1 => $pipes[1], 2 => $pipes[2]];

        fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
    }

    /**
     * Waits until the worker reports the given phase.
     *
     * @param string $phase Phase name emitted by the worker.
     * @param int $timeout Maximum wait in seconds.
     *
     * @throws RuntimeException if the worker exits, emits an invalid message, or the wait times out.
     *
     * @return array<string, mixed> Message reported for the phase.
     */
    public function await(string $phase, int $timeout = 15): array
    {
        $deadline = microtime(true) + $timeout;

        do {
            $output = stream_get_contents($this->pipes[1]);

            $this->buffer .= $output === false ? '' : $output;

            while (($position = strpos($this->buffer, "\n")) !== false) {
                $line = substr($this->buffer, 0, $position);

                $this->buffer = substr($this->buffer, $position + 1);

                $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                if (
                    is_array($message) === false
                    || isset($message['phase']) === false
                    || is_string($message['phase']) === false
                ) {
                    throw new RuntimeException('Invalid worker message.');
                }

                $values = [];

                foreach ($message as $name => $value) {
                    if (is_string($name)) {
                        $values[$name] = $value;
                    }
                }

                $this->messages[$message['phase']] = $values;
            }

            if (isset($this->messages[$phase])) {
                return $this->messages[$phase];
            }

            if ($this->isRunning() === false) {
                $error = stream_get_contents($this->pipes[2]);

                throw new RuntimeException(
                    'Worker ended before ' . $phase . ': '
                    . json_encode($this->messages, JSON_THROW_ON_ERROR)
                    . ($error === false ? '' : $error),
                );
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Timed out waiting for worker phase: ' . $phase);
    }

    /**
     * Terminates the worker if it is still running and releases its pipes.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        if ($this->isRunning()) {
            proc_terminate($this->process);
            usleep(100000);

            if ($this->isRunning()) {
                proc_terminate($this->process, 9);
            }
        }

        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }

        proc_close($this->process);

        $this->closed = true;
    }

    /**
     * @phpstan-impure
     */
    private function isRunning(): bool
    {
        return proc_get_status($this->process)['running'];
    }
}
