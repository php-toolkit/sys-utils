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
use function chdir;
use function pclose;
use function popen;

/**
 * Class SysFunc
 *
 * @package Toolkit\Sys\Proc
 */
class SysFunc
{
    /**
     * @param string $workDir
     */
    public static function chdir(string $workDir): void
    {
        if (false === chdir($workDir)) {
            throw new RuntimeException('chdir execute failure, dir: ' . $workDir);
        }
    }

    /**
     * @param string $command
     * @param string $mode 'r': read, 'w': write
     *
     * @return false|resource
     * @see https://www.php.net/manual/zh/function.popen.php
     */
    public static function popen(string $command, string $mode)
    {
        $handle = popen($command, $mode);
        if (false === $handle) {
            throw new RuntimeException('popen execute failure, cmd: ' . $command);
        }

        return $handle;
    }

    /**
     * @param resource $handle
     *
     * @return int
     * @see https://www.php.net/manual/zh/function.pclose.php
     */
    public static function pclose($handle): int
    {
        return pclose($handle);
    }
}
