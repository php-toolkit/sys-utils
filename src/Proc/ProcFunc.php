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
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;

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
     * @return array
     * @see https://www.php.net/manual/en/function.proc-get-status.php
     */
    public static function getStatus($process): array
    {
        return (array)proc_get_status($process);
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
     *
     * @return bool
     * @see https://www.php.net/manual/en/function.proc-terminate.php
     */
    public static function terminate($process): bool
    {
        return proc_terminate($process);
    }
}
