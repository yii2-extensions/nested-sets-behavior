<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\support;

use RuntimeException;

/**
 * Runs a database writer in a separate PHP process that reports through files, since Windows cannot read process pipes
 * without blocking.
 */
final class ConcurrentProcess
{
    private string $buffer = '';

    private bool $closed = false;

    private string $errors;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $messages = [];

    private int $offset = 0;

    private string $output;

    /**
     * @var resource
     */
    private mixed $process;

    /**
     * @param array<string, mixed> $payload Worker options encoded as JSON on the worker's standard input.
     * @param string $directory Existing per-test directory that receives the worker's output and error files.
     */
    public function __construct(array $payload, string $directory)
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

        $base = $directory . '/worker-' . bin2hex(random_bytes(4));

        $this->output = $base . '.out';
        $this->errors = $base . '.err';

        // PHP warnings and startup notices of the worker go to the error file, so they cannot corrupt the JSON report.
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=stderr', __DIR__ . '/concurrency-worker.php', $bootstrap],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->output, 'w'],
                2 => ['file', $this->errors, 'w'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );

        if (is_resource($process) === false || isset($pipes[0]) === false) {
            throw new RuntimeException(
                'Cannot start concurrency worker.',
            );
        }

        $this->process = $process;

        fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
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
            $this->collect();

            if (isset($this->messages[$phase])) {
                return $this->messages[$phase];
            }

            if ($this->isRunning() === false) {
                // The worker may report the phase and exit between the read above and the status check.
                $this->collect();

                if (isset($this->messages[$phase])) {
                    return $this->messages[$phase];
                }

                throw new RuntimeException(
                    "Worker ended before {$phase}: "
                    . json_encode($this->messages, JSON_THROW_ON_ERROR)
                    . $this->readErrors(),
                );
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException(
            "Timed out waiting for worker phase: {$phase}: "
            . json_encode($this->messages, JSON_THROW_ON_ERROR)
            . $this->readErrors(),
        );
    }

    /**
     * Terminates the worker if it is still running and releases its process handle.
     *
     * The caller owns the directory and removes the worker's output and error files after closing it.
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

        proc_close($this->process);

        $this->closed = true;
    }

    /**
     * Appends newly reported output to the line buffer and stores every complete worker message.
     *
     * @throws RuntimeException if the output cannot be read or a line is not a valid worker message.
     */
    private function collect(): void
    {
        $this->buffer .= $this->readOutput();

        while (($position = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $position);

            $this->buffer = substr($this->buffer, $position + 1);

            $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            if (
                is_array($message) === false
                || isset($message['phase']) === false
                || is_string($message['phase']) === false
            ) {
                throw new RuntimeException(
                    'Invalid worker message.',
                );
            }

            $values = [];

            foreach ($message as $name => $value) {
                if (is_string($name)) {
                    $values[$name] = $value;
                }
            }

            $this->messages[$message['phase']] = $values;
        }
    }

    /**
     * @phpstan-impure
     */
    private function isRunning(): bool
    {
        return proc_get_status($this->process)['running'];
    }

    /**
     * Reads the worker's error output for diagnostics.
     *
     * @return string Error output, an empty string when the file is missing, or a marker when it cannot be read.
     */
    private function readErrors(): string
    {
        if (is_file($this->errors) === false) {
            return '';
        }

        $errors = file_get_contents($this->errors);

        return $errors === false ? '[worker error output unreadable]' : $errors;
    }

    /**
     * Reads the bytes the worker appended to its output file since the previous read.
     *
     * @throws RuntimeException if the output file cannot be inspected or read.
     *
     * @return string Newly appended bytes, or an empty string when there are none.
     */
    private function readOutput(): string
    {
        clearstatcache(true, $this->output);

        if (is_file($this->output) === false) {
            return '';
        }

        $size = filesize($this->output);

        if ($size === false) {
            throw new RuntimeException(
                'Cannot stat worker output.',
            );
        }

        if ($size <= $this->offset) {
            return '';
        }

        $output = file_get_contents($this->output, false, null, $this->offset);

        if ($output === false) {
            throw new RuntimeException(
                'Cannot read worker output.',
            );
        }

        $this->offset += strlen($output);

        return $output;
    }
}
