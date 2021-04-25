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
use function exec;
use function function_exists;
use function implode;
use function is_file;
use function ob_start;
use function preg_match;
use function preg_replace;
use function shell_exec;
use function system;
use function trim;
use const DIRECTORY_SEPARATOR;

/**
 * Class Sys
 *
 * @package Toolkit\Sys\Proc
 */
class Sys extends SysEnv
{
    /**
     * @param string      $command
     * @param null|string $logfile
     * @param null|string $user
     *
     * @return mixed
     * @throws RuntimeException
     */
    public static function exec($command, $logfile = null, $user = null)
    {
        // If should run as another user, we must be on *nix and must have sudo privileges.
        $suDo = '';
        if ($user && SysEnv::isUnix() && SysEnv::isRoot()) {
            $suDo = "sudo -u $user";
        }

        // Start execution. Run in foreground (will block).
        $logfile = $logfile ?: SysEnv::getNullDevice();

        // Start execution. Run in foreground (will block).
        exec("$suDo $command 1>> \"$logfile\" 2>&1", $dummy, $retVal);

        if ($retVal !== 0) {
            throw new RuntimeException("command exited with status '$retVal'.");
        }

        return $dummy;
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
     * @param string      $command
     * @param bool        $returnStatus
     * @param string|null $cwd
     *
     * @return array|string
     */
    public static function execute(string $command, bool $returnStatus = true, string $cwd = '')
    {
        $status = 1;

        if ($cwd) {
            chdir($cwd);
        }

        // system
        if (function_exists('system')) {
            ob_start();
            system($command, $status);
            $output = ob_get_clean();
        //exec
        } elseif (function_exists('exec')) {
            exec($command, $output, $status);
            $output = implode("\n", $output);

        //shell_exec
        } elseif (function_exists('shell_exec')) {
            $output = shell_exec($command);
        } else {
            $status = -1;
            $output = 'Command execution not possible on this system';
        }

        if ($returnStatus) {
            return [
                'output' => trim($output),
                'status' => $status
            ];
        }

        return trim($output);
    }

    /**
     * get bash is available
     *
     * @return bool
     */
    public static function shIsAvailable(): bool
    {
        // $checkCmd = "/usr/bin/env bash -c 'echo OK'";
        // $shell = 'echo $0';
        $checkCmd = "sh -c 'echo OK'";

        return self::execute($checkCmd, false) === 'OK';
    }

    /**
     * get bash is available
     *
     * @return bool
     */
    public static function bashIsAvailable(): bool
    {
        // $checkCmd = "/usr/bin/env bash -c 'echo OK'";
        // $shell = 'echo $0';
        $checkCmd = "bash -c 'echo OK'";

        return self::execute($checkCmd, false) === 'OK';
    }

    /**
     * @return string
     */
    public static function getOutsideIP(): string
    {
        [$code, $output] = self::run('ip addr | grep eth0');

        if ($code === 0 && $output && preg_match('#inet (.*)\/#', $output, $ms)) {
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
            $cmd = "open \"{$pageUrl}\"";
        } elseif (self::isWin()) {
            // $cmd = 'cmd /c start';
            $cmd = "start {$pageUrl}";
        } else {
            $cmd = "x-www-browser \"{$pageUrl}\"";
        }

        // Show::info("Will open the page on browser:\n  $pageUrl");
        self::execute($cmd);
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
        static $size;
        if ($size !== null && !$refresh) {
            return $size;
        }

        if (self::shIsAvailable()) {
            // try stty if available
            $stty = [];

            if (exec('stty -a 2>&1', $stty) && preg_match(
                '/rows\s+(\d+);\s*columns\s+(\d+);/mi',
                implode(' ', $stty),
                $matches
            )
            ) {
                return ($size = [$matches[2], $matches[1]]);
            }

            // fallback to tput, which may not be updated on terminal resize
            if (($width = (int)exec('tput cols 2>&1')) > 0 && ($height = (int)exec('tput lines 2>&1')) > 0) {
                return ($size = [$width, $height]);
            }

            // fallback to ENV variables, which may not be updated on terminal resize
            if (($width = (int)getenv('COLUMNS')) > 0 && ($height = (int)getenv('LINES')) > 0) {
                return ($size = [$width, $height]);
            }
        }

        if (SysEnv::isWindows()) {
            $output = [];
            exec('mode con', $output);

            if (isset($output[1]) && strpos($output[1], 'CON') !== false) {
                return ($size = [
                    (int)preg_replace('~\D~', '', $output[3]),
                    (int)preg_replace('~\D~', '', $output[4])
                ]);
            }
        }

        return ($size = false);
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
