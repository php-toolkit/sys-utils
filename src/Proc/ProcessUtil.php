<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

namespace Toolkit\Sys\Proc;

use JetBrains\PhpStorm\NoReturn;
use RuntimeException;
use Toolkit\Stdlib\Helper\Assert;
use Toolkit\Stdlib\OS;
use function array_merge;
use function cli_set_process_title;
use function error_get_last;
use function file_exists;
use function file_get_contents;
use function function_exists;
use function pcntl_alarm;
use function posix_geteuid;
use function posix_getgrnam;
use function posix_getpwnam;
use function posix_getpwuid;
use function posix_getuid;
use function posix_kill;
use function posix_setgid;
use function posix_setuid;
use function time;
use function unlink;
use function usleep;
use const DIRECTORY_SEPARATOR;
use const PHP_OS;

/**
 * Class ProcessUtil
 *
 * @package Toolkit\Sys\Proc
 */
class ProcessUtil extends PcntlFunc
{
    /**
     * @var array
     */
    public static array $signalMap = [
        Signal::INT  => 'SIGINT(Ctrl+C)',
        Signal::TERM => 'SIGTERM',
        Signal::KILL => 'SIGKILL',
        Signal::STOP => 'SIGSTOP',
    ];

    /**
     * whether TTY is supported on the current operating system.
     *
     * @var bool|null
     */
    private static ?bool $ttySupported = null;

    /**
     * Returns whether TTY is supported on the current operating system.
     */
    public static function isTtySupported(): bool
    {
        if (null === self::$ttySupported) {
            $proc = @proc_open('echo 1 >/dev/null', [
                ['file', '/dev/tty', 'r'],
                ['file', '/dev/tty', 'w'],
                ['file', '/dev/tty', 'w']
            ], $pipes);

            if ($proc) {
                self::$ttySupported = true;
                ProcFunc::closePipes($pipes);
                ProcFunc::close($proc);
            } else {
                self::$ttySupported = false;
            }
        }

        return self::$ttySupported;
    }

    /**
     * whether PTY is supported on the current operating system.
     *
     * @var bool|null
     */
    private static ?bool $ptySupported = null;

    /**
     * Returns whether PTY is supported on the current operating system.
     *
     * @return bool
     */
    public static function isPtySupported(): bool
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            return self::$ptySupported = false;
        }

        if (null === self::$ptySupported) {
            $proc = @proc_open('echo 1 >/dev/null', [['pty'], ['pty'], ['pty']], $pipes);

            if ($proc) {
                self::$ptySupported = true;
                ProcFunc::closePipes($pipes);
                ProcFunc::close($proc);
            } else {
                self::$ptySupported = false;
            }
        }

        return self::$ptySupported;
    }

    /**************************************************************************************
     * basic signal methods
     *************************************************************************************/

    /**
     * send kill signal to the process
     *
     * @param int $pid
     * @param bool $force
     * @param int $timeout
     *
     * @return bool
     */
    public static function kill(int $pid, bool $force = false, int $timeout = 3): bool
    {
        return self::sendSignal($pid, $force ? Signal::KILL : Signal::TERM, $timeout);
    }

    /**
     * Do shutdown process and wait it exit.
     *
     * @param int $pid Master Pid
     * @param bool $force
     * @param int $waitTime
     * @param null $error
     * @param string $name
     *
     * @return bool
     */
    public static function killAndWait(
        int $pid,
        &$error = null,
        string $name = 'process',
        bool $force = false,
        int $waitTime = 10
    ): bool {
        // do stop
        if (!self::kill($pid, $force)) {
            $error = "Send stop signal to the $name(PID:$pid) failed!";
            return false;
        }

        // not wait, only send signal
        if ($waitTime <= 0) {
            $error = "The $name process stopped";
            return true;
        }

        $startTime = time();
        echo 'Stopping .';

        // wait exit
        while (true) {
            if (!self::isRunning($pid)) {
                break;
            }

            if (time() - $startTime > $waitTime) {
                $error = "Stop the $name(PID:$pid) failed(timeout)!";
                break;
            }

            echo '.';
            sleep(1);
        }

        return $error === null;
    }

    /**
     * Stops all running children process
     *
     * **examples**
     *
     * - param `$children` examples:
     *
     * ```php
     * [
     *  pid1 => [id => 1,],
     *  pid2 => [id => 2,],
     * ]
     * ```
     *
     * - param `$events` examples:
     *
     * ```php
     *  [
     *    'beforeStops' => function ($sigText) {
     *      echo "Stopping processes({$sigText}) ...\n";
     *    },
     *    'beforeStop' => function ($pid, $info) {
     *      echo "Stopping process(PID:$pid)\n";
     *    }
     * ]
     * ```
     *
     * @param array<int, array{id:int, pid:int}> $children
     * @param int $signal
     * @param array{beforeStops: callable(string):void, beforeStop: callable(int,array):void} $events
     *
     * @return bool
     */
    public static function stopWorkers(array $children, int $signal = Signal::TERM, array $events = []): bool
    {
        if (!$children) {
            return false;
        }

        $events = array_merge([
            'beforeStops' => null,
            'beforeStop'  => null,
        ], $events);

        if ($cb = $events['beforeStops']) {
            $cb($signal, self::$signalMap[$signal]);
        }

        foreach ($children as $pid => $child) {
            if ($cb = $events['beforeStop']) {
                $cb($pid, $child);
            }

            // send exit signal.
            self::sendSignal($pid, $signal);
        }

        return true;
    }

    /**
     * @param int $pid
     *
     * @return bool
     */
    public static function isRunning(int $pid): bool
    {
        return ($pid > 0) && @posix_kill($pid, 0);
    }

    /**
     * exit
     *
     * @param int $code
     */
    #[NoReturn]
    public static function quit(int $code = 0): void
    {
        exit($code);
    }

    /**
     * 杀死所有进程
     *
     * @param string $name
     * @param int $sigNo
     *
     * @return string
     */
    public static function killByName(string $name, int $sigNo = 9): string
    {
        $cmd = 'ps -eaf |grep "' . $name . '" | grep -v "grep"| awk "{print $2}" | xargs kill -' . $sigNo;

        return exec($cmd);
    }

    /**************************************************************************************
     * process signal handle
     *************************************************************************************/

    /**
     * send signal to the process
     *
     * @param int $pid
     * @param int $signal
     * @param int $timeout
     *
     * @return bool
     */
    public static function sendSignal(int $pid, int $signal, int $timeout = 0): bool
    {
        Assert::intShouldGt0($pid, 'pid', true);
        Assert::intShouldGte0($timeout, 'timeout', true);

        if (!self::hasPosix()) {
            return false;
        }

        // do send
        if ($ret = posix_kill($pid, $signal)) {
            return true;
        }

        // failed, try again ...
        $timeout   = $timeout > 0 && $timeout < 10 ? $timeout : 3;
        $startTime = time();

        // retry stop if not stopped.
        while (true) {
            // success
            if (!posix_kill($pid, 0)) {
                break;
            }

            // have been timeout
            if ((time() - $startTime) >= $timeout) {
                return false;
            }

            // try again kill
            $ret = posix_kill($pid, $signal);
            usleep(10000);
        }

        return $ret;
    }

    /**
     * @param int $pid
     * @param int $signal
     *
     * @return bool
     */
    public static function simpleKill(int $pid, int $signal): bool
    {
        if (!self::hasPosix()) {
            return false;
        }

        return posix_kill($pid, $signal);
    }

    /**************************************************************************************
     * basic process method
     *************************************************************************************/

    /**
     * get PID by pid File
     *
     * @param string $file
     * @param bool $checkLive
     *
     * @return int
     */
    public static function getPidByFile(string $file, bool $checkLive = false): int
    {
        if ($file && file_exists($file)) {
            $pid = (int)file_get_contents($file);

            // check live
            if ($checkLive && self::isRunning($pid)) {
                return $pid;
            }

            unlink($file);
        }

        return 0;
    }

    /**
     * Get unix user of current process.
     *
     * @return array
     */
    public static function getCurrentUser(): array
    {
        return posix_getpwuid(posix_getuid());
    }

    /**
     * delay do something.
     *
     * ```php
     * ProcessUtil::afterDo(300, function () {
     *      static $i = 0;
     *      echo "#{$i} alarm\n";
     *      $i++;
     *      if ($i > 20) {
     *          ProcessUtil::clearAlarm(); // close
     *      }
     *  });
     * ```
     *
     * @param int $seconds
     * @param callable $handler
     *
     * @return bool|int
     */
    public static function afterDo(int $seconds, callable $handler): bool|int
    {
        self::installSignal(Signal::ALRM, $handler);
        return pcntl_alarm($seconds);
    }

    /**
     * Set process title. alias of setTitle()
     *
     * @param string $title
     *
     * @return bool
     */
    public static function setName(string $title): bool
    {
        return self::setTitle($title);
    }

    /**
     * Set process title.
     *
     * @param string $title
     *
     * @return bool
     */
    public static function setTitle(string $title): bool
    {
        if (!$title) {
            return false;
        }

        if ('Darwin' === PHP_OS) {
            return false;
        }

        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        } elseif (function_exists('setproctitle')) {
            setproctitle($title);
        }

        if ($error = error_get_last()) {
            throw new RuntimeException($error['message']);
        }

        return true;
    }

    /**
     * Set unix user and group for current process script.
     *
     * @param string $user
     * @param string $group
     *
     * @throws RuntimeException
     */
    public static function changeScriptOwner(string $user, string $group = ''): void
    {
        $uInfo = posix_getpwnam($user);
        if (!$uInfo || !isset($uInfo['uid'])) {
            throw new RuntimeException("User ($user) not found.");
        }

        $uid = (int)$uInfo['uid'];

        // Get gid.
        if ($group) {
            if (!$gInfo = posix_getgrnam($group)) {
                throw new RuntimeException("Group $group not exists", -300);
            }

            $gid = (int)$gInfo['gid'];
        } else {
            $gid = (int)$uInfo['gid'];
        }

        if (!posix_initgroups($uInfo['name'], $gid)) {
            throw new RuntimeException("The user [$user] is not in the user group ID [GID:$gid]", -300);
        }

        posix_setgid($gid);
        if (posix_geteuid() !== $gid) {
            throw new RuntimeException("Unable to change group to $user (UID: $gid).", -300);
        }

        posix_setuid($uid);
        if (posix_geteuid() !== $uid) {
            throw new RuntimeException("Unable to change user to $user (UID: $uid).", -300);
        }
    }

    /**
     * @return bool
     */
    public static function hasPosix(): bool
    {
        return !OS::isWindows() && function_exists('posix_kill');
    }
}
