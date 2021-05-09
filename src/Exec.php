<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

namespace Toolkit\Sys;

use RuntimeException;
use Toolkit\Sys\Proc\ProcWrapper;
use function chdir;
use function implode;
use function ob_get_clean;
use function ob_start;
use function pclose;
use function popen;
use function system;
use function exec;

/**
 * Class Exec
 *
 * @package Toolkit\Sys
 */
class Exec
{
    /**
     * @param string $command
     * @param string $workDir
     * @param bool   $outAsString
     *
     * @return array
     */
    public static function exec(string $command, string $workDir = '', bool $outAsString = true): array
    {
        if ($workDir) {
            chdir($workDir);
        }

        exec($command, $output, $status);

        return [$status, $outAsString ? implode("\n", $output) : $output];
    }

    /**
     * @param string $command
     * @param string $workDir
     * @param bool   $allReturn
     *
     * @return array
     */
    public static function system(string $command, string $workDir = '', bool $allReturn = false): array
    {
        if ($workDir) {
            chdir($workDir);
        }

        if ($allReturn) {
            ob_start();
            system($command, $status);
            $output = ob_get_clean();
        } else {
            // only last line message
            $output = system($command, $status);
        }

        return [$status, $output];
    }

    /**
     * @param string $command
     * @param string $workDir
     *
     * @return string|null
     */
    public static function shellExec(string $command, string $workDir = ''): ?string
    {
        if ($workDir) {
            chdir($workDir);
        }

        return shell_exec($command);
    }

    /**
     * run a command in background
     *
     * @param string $cmd
     */
    public static function bgExec(string $cmd): void
    {
        self::inBackground($cmd);
    }

    /**
     * run a command in background
     *
     * @param string $cmd
     */
    public static function inBackground(string $cmd): void
    {
        if (SysEnv::isWindows()) {
            pclose(popen('start /B ' . $cmd, 'r'));
        } else {
            exec($cmd . ' > /dev/null &');
        }
    }

    /**
     * run a command. it is support windows
     *
     * @param string $command
     * @param string $cwd
     *
     * @return array [$code, $output, $error]
     * @throws RuntimeException
     */
    public static function run(string $command, string $cwd = ''): array
    {
        return ProcWrapper::runCmd($command, $cwd);
    }
}
