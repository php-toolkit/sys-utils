<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

namespace Toolkit\Sys\Proc;

use RuntimeException;
use function array_keys;
use function fclose;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_get_contents;

/**
 * Class ProcFunc
 *
 * @package Toolkit\Sys\Proc
 */
class ProcFunc
{
    /**
     * @param string $cmd
     * @param array  $descriptorSpec
     * @param array  $pipes
     * @param string $workDir
     * @param array  $env
     * @param array  $otherOptions
     *
     * @return resource
     * @see https://www.php.net/manual/en/function.proc-open.php
     */
    public static function open(
        string $cmd,
        array $descriptorSpec,
        array &$pipes,
        string $workDir = '',
        array $env = [],
        array $otherOptions = []
    ) {
        $process = proc_open($cmd, $descriptorSpec, $pipes, $workDir, $env, $otherOptions);

        if (!is_resource($process)) {
            throw new RuntimeException("Can't open resource with proc_open.");
        }

        return $process;
    }

    /**
     * Get information about a process opened by `proc_open`
     *
     * @param resource $process
     *
     * @return array{command:string,pid:int,running:bool,signaled:bool,stopped:bool,exitcode:int,termsig:int,stopsig:int}
     * @see https://www.php.net/manual/en/function.proc-get-status.php
     */
    public static function getStatus($process): array
    {
        return proc_get_status($process);
    }

    /**
     * @param resource $pipe
     *
     * @return string
     */
    public static function readClosePipe($pipe): string
    {
        return self::readPipe($pipe, true);
    }

    /**
     * @param resource $pipe
     * @param bool $close
     *
     * @return string
     */
    public static function readPipe($pipe, bool $close = false): string
    {
        $output = stream_get_contents($pipe);
        $close && fclose($pipe);
        return $output;
    }

    /**
     * @param array<resource> $pipes
     */
    public static function closePipes(array $pipes): void
    {
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
    }

    /**
     * @param resource $pipe Pipe from proc_open()
     */
    public static function closePipe($pipe): bool
    {
        return fclose($pipe);
    }

    /**
     * Close a process opened by `proc_open` and return the exit code of that process
     *
     * @param resource $process
     *
     * @return int
     * @see https://www.php.net/manual/en/function.proc-close.php
     */
    public static function close($process): int
    {
        return proc_close($process);
    }

    /**
     * Kills a process opened by `proc_open`
     *
     * @param resource $process
     * @param int $signal
     *
     * @return bool
     * @see https://www.php.net/manual/en/function.proc-terminate.php
     */
    public static function terminate($process, int $signal = 15): bool
    {
        return proc_terminate($process, $signal);
    }
}
