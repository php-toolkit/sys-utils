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
use Toolkit\Sys\Util\ShellUtil;
use function exec;
use function is_file;
use function preg_match;
use const DIRECTORY_SEPARATOR;

/**
 * Class Sys
 *
 * @package Toolkit\Sys\Proc
 */
class Sys extends SysEnv
{
    /**
     * @param string $command
     * @param string $logfile
     * @param string $user
     *
     * @return mixed
     * @throws RuntimeException
     */
    public static function execWithSudo(string $command, string $logfile = '', string $user = '')
    {
        return \Toolkit\Sys\Exec::execWithSudo($command, $logfile, $user);
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

    /**
     * Method to execute a command in the sys
     * Uses :
     * 1. system
     * X. passthru - will report error on windows
     * 3. exec
     * 4. shell_exec
     *
     * @param string $command
     * @param bool   $returnStatus
     * @param string $cwd
     *
     * @return array|string
     */
    public static function execute(string $command, bool $returnStatus = true, string $cwd = '')
    {
        return \Toolkit\Sys\Exec::auto($command, $returnStatus, $cwd);
    }

    /**
     * get bash is available
     *
     * @return bool
     * @deprecated please use ShellUtil::shIsAvailable()
     */
    public static function shIsAvailable(): bool
    {
        return ShellUtil::shIsAvailable();
    }

    /**
     * get bash is available
     *
     * @return bool
     * @deprecated please use ShellUtil::bashIsAvailable()
     */
    public static function bashIsAvailable(): bool
    {
        return ShellUtil::bashIsAvailable();
    }

    /**
     * @return string
     */
    public static function getOutsideIP(): string
    {
        [$code, $out] = self::run('ip addr | grep eth0');

        if ($code === 0 && $out && preg_match('#inet (.*)\/#', $out, $ms)) {
            return $ms[1];
        }

        return 'unknown';
    }

    /**
     * Open browser URL
     *
     * Mac：
     * open 'https://swoft.org'
     *
     * Linux:
     * x-www-browser 'https://swoft.org'
     *
     * Windows:
     * cmd /c start https://swoft.org
     *
     * @param string $pageUrl
     */
    public static function openBrowser(string $pageUrl): void
    {
        if (self::isMac()) {
            $cmd = "open \"$pageUrl\"";
        } elseif (self::isWin()) {
            // $cmd = 'cmd /c start';
            $cmd = "start $pageUrl";
        } else {
            $cmd = "x-www-browser \"$pageUrl\"";
        }

        // Show::info("Will open the page on browser:\n  $pageUrl");
        \Toolkit\Sys\Exec::auto($cmd);
    }

    /**
     * get screen size
     *
     * ```php
     * list($width, $height) = Sys::getScreenSize();
     * ```
     *
     * @from Yii2
     *
     * @param boolean $refresh whether to force checking and not re-use cached size value.
     *                         This is useful to detect changing window size while the application is running but may
     *                         not get up to date values on every terminal.
     *
     * @return array|boolean An array of ($width, $height) or false when it was not able to determine size.
     */
    public static function getScreenSize(bool $refresh = false)
    {
        return ShellUtil::getScreenSize($refresh);
    }

    /**
     * @param string $program
     *
     * @return int|string
     */
    public static function getCpuUsage(string $program)
    {
        if (!$program) {
            return -1;
        }

        return exec('ps aux | grep ' . $program . ' | grep -v grep | grep -v su | awk {"print $3"}');
    }

    /**
     * @param string $program
     *
     * @return int|string
     */
    public static function getMemUsage(string $program)
    {
        if (!$program) {
            return -1;
        }

        return exec('ps aux | grep ' . $program . ' | grep -v grep | grep -v su | awk {"print $4"}');
    }

    /**
     * find executable file by input
     *
     * Usage:
     *
     * ```php
     * $phpBin = Sys::findExecutable('php');
     * echo $phpBin; // "/usr/bin/php"
     * ```
     *
     * @param string $name
     * @param array  $paths The dir paths for find bin file. if empty, will read from env $PATH
     *
     * @return string
     */
    public static function findExecutable(string $name, array $paths = []): string
    {
        $paths = $paths ?: self::getEnvPaths();

        foreach ($paths as $path) {
            $filename = $path . DIRECTORY_SEPARATOR . $name;
            if (is_file($filename)) {
                return $filename;
            }
        }
        return "";
    }

    /**
     * @param string $name
     * @param array  $paths
     *
     * @return bool
     */
    public static function isExecutable(string $name, array $paths = []): bool
    {
        return self::findExecutable($name, $paths) !== "";
    }
}
