<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

namespace Toolkit\Sys\Proc;

use Closure;
use RuntimeException;
use Toolkit\Stdlib\OS;
use function array_merge;
use function cli_set_process_title;
use function error_get_last;
use function file_exists;
use function file_get_contents;
use function function_exists;
use function getmypid;
use function pcntl_alarm;
use function pcntl_async_signals;
use function pcntl_fork;
use function pcntl_signal;
use function pcntl_signal_dispatch;
use function pcntl_signal_get_handler;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function posix_geteuid;
use function posix_getgrnam;
use function posix_getpid;
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
class ProcessUtil
{
    /**
     * @var array
     */
    public static $signalMap = [
        Signal::INT  => 'SIGINT(Ctrl+C)',
        Signal::TERM => 'SIGTERM',
        Signal::KILL => 'SIGKILL',
        Signal::STOP => 'SIGSTOP',
    ];

    /**
     * Returns whether TTY is supported on the current operating system.
     */
    public static function isTtySupported(): bool
    {
        static $isTtySupported;

        if (null === $isTtySupported) {
            $isTtySupported = (bool)@proc_open('echo 1 >/dev/null', [
                ['file', '/dev/tty', 'r'],
                ['file', '/dev/tty', 'w'],
                ['file', '/dev/tty', 'w']
            ], $pipes);
        }

        return $isTtySupported;
    }

    /**
     * Returns whether PTY is supported on the current operating system.
     *
     * @return bool
     */
    public static function isPtySupported(): bool
    {
        static $result;

        if (null !== $result) {
            return $result;
        }

        if ('\\' === DIRECTORY_SEPARATOR) {
            return $result = false;
        }

        return $result = (bool)@proc_open('echo 1 >/dev/null', [['pty'], ['pty'], ['pty']], $pipes);
    }

    /**********************************************************************
     * create child process `pcntl`
     *********************************************************************/

    /**
     * fork/create a child process.
     *
     * @param callable|null $onStart Will running on the child process start.
     * @param callable|null $onError
     * @param int           $id      The process index number. will use `forks()`
     *
     * @return array|false
     * @throws RuntimeException
     */
    public static function fork(callable $onStart = null, callable $onError = null, $id = 0)
    {
        if (!self::hasPcntl()) {
            return false;
        }

        $info = [];
        $pid  = pcntl_fork();

        // at parent, get forked child info
        if ($pid > 0) {
            $info = [
                'id'        => $id,
                'pid'       => $pid,
                'startTime' => time(),
            ];
        } elseif ($pid === 0) { // at child
            $pid = self::getPid();

            if ($onStart) {
                $onStart($pid, $id);
            }
        } else {
            if ($onError) {
                $onError($pid);
            }

            throw new RuntimeException('Fork child process failed! exiting.');
        }

        return $info;
    }

    /**
     * @param callable|null $onStart
     * @param callable|null $onError
     * @param int           $id
     *
     * @return array|false
     * @see ProcessUtil::fork()
     */
    public static function create(callable $onStart = null, callable $onError = null, $id = 0)
    {
        return self::fork($onStart, $onError, $id);
    }

    /**
     * Daemon, detach and run in the background
     *
     * @param Closure|null $beforeQuit
     *
     * @return int Return new process PID
     * @throws RuntimeException
     */
    public static function daemonRun(Closure $beforeQuit = null): int
    {
        if (!self::hasPcntl()) {
            return 0;
        }

        // umask(0);
        $pid = pcntl_fork();

        switch ($pid) {
            case 0: // at new process
                $pid = self::getPid();

                if (posix_setsid() < 0) {
                    throw new RuntimeException('posix_setsid() execute failed! exiting');
                }

                // chdir('/');
                // umask(0);
                break;
            case -1: // fork failed.
                throw new RuntimeException('Fork new process is failed! exiting');
            default: // at parent
                if ($beforeQuit) {
                    $beforeQuit($pid);
                }
                exit(0);
        }

        return $pid;
    }

    /**
     * @param int           $number
     * @param callable|null $onStart
     * @param callable|null $onError
     *
     * @return array|false
     * @throws RuntimeException
     * @see ProcessUtil::forks()
     */
    public static function multi(int $number, callable $onStart = null, callable $onError = null)
    {
        return self::forks($number, $onStart, $onError);
    }

    /**
     * fork/create multi child processes.
     *
     * @param int           $number
     * @param callable|null $onStart Will running on the child processes.
     * @param callable|null $onError
     *
     * @return array|false
     * @throws RuntimeException
     */
    public static function forks(int $number, callable $onStart = null, callable $onError = null)
    {
        if ($number <= 0) {
            return false;
        }

        if (!self::hasPcntl()) {
            return false;
        }

        $pidAry = [];
        for ($id = 0; $id < $number; $id++) {
            $info = self::fork($onStart, $onError, $id);
            // log
            $pidAry[$info['pid']] = $info;
        }

        return $pidAry;
    }

    /**
     * wait child exit.
     *
     * @param callable $onExit Exit callback. will received args: (pid, exitCode, status)
     *
     * @return bool
     */
    public static function wait(callable $onExit): bool
    {
        if (!self::hasPcntl()) {
            return false;
        }

        $status = null;

        // pid < 0：子进程都没了
        // pid > 0：捕获到一个子进程退出的情况
        // pid = 0：没有捕获到退出的子进程
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) >= 0) {
            if ($pid) {
                // handler(pid, exitCode, status)
                $onExit($pid, pcntl_wexitstatus($status), $status);
            } else {
                usleep(50000);
            }
        }

        return true;
    }

    /**
     * Stops all running children
     *
     * @param array $children
     *      [
     *      'pid' => [
     *      'id' => worker id
     *      ],
     *      ... ...
     *      ]
     * @param int   $signal
     * @param array $events
     *      [
     *      'beforeStops' => function ($sigText) {
     *      echo "Stopping processes({$sigText}) ...\n";
     *      },
     *      'beforeStop' => function ($pid, $info) {
     *      echo "Stopping process(PID:$pid)\n";
     *      }
     *      ]
     *
     * @return bool
     */
    public static function stopWorkers(array $children, int $signal = Signal::TERM, array $events = []): bool
    {
        if (!$children) {
            return false;
        }

        if (!self::hasPcntl()) {
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

    /**************************************************************************************
     * basic signal methods
     *************************************************************************************/

    /**
     * send kill signal to the process
     *
     * @param int  $pid
     * @param bool $force
     * @param int  $timeout
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
     * @param int    $pid Master Pid
     * @param bool   $force
     * @param int    $waitTime
     * @param null   $error
     * @param string $name
     *
     * @return bool
     */
    public static function killAndWait(
        int $pid,
        &$error = null,
        $name = 'process',
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

        if ($error) {
            return false;
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
    public static function quit($code = 0): void
    {
        exit((int)$code);
    }

    /**
     * 杀死所有进程
     *
     * @param     $name
     * @param int $sigNo
     *
     * @return string
     */
    public static function killByName(string $name, int $sigNo = 9): string
    {
        $cmd = 'ps -eaf |grep "' . $name . '" | grep -v "grep"| awk "{print $2}"|xargs kill -' . $sigNo;

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
        if ($pid <= 0 || !self::hasPosix()) {
            return false;
        }

        // do send
        if ($ret = posix_kill($pid, $signal)) {
            return true;
        }

        // don't want retry
        if ($timeout <= 0) {
            return $ret;
        }

        // failed, try again ...
        $timeout   = $timeout > 0 && $timeout < 10 ? $timeout : 3;
        $startTime = time();

        // retry stop if not stopped.
        while (true) {
            // success
            if (!$isRunning = @posix_kill($pid, 0)) {
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
     * install signal
     *
     * @param int      $signal e.g: SIGTERM SIGINT(Ctrl+C) SIGUSR1 SIGUSR2 SIGHUP
     * @param callable $handler
     *
     * @return bool
     */
    public static function installSignal($signal, callable $handler): bool
    {
        if (!self::hasPcntl()) {
            return false;
        }

        return pcntl_signal($signal, $handler, false);
    }

    /**
     * dispatch signal
     *
     * @return bool
     */
    public static function dispatchSignal(): bool
    {
        if (!self::hasPcntl()) {
            return false;
        }

        // receive and dispatch signal
        return pcntl_signal_dispatch();
    }

    /**
     * get signal handler
     *
     * @param int $signal
     *
     * @return bool|string|mixed
     * @since 7.1
     */
    public static function getSignalHandler(int $signal)
    {
        return pcntl_signal_get_handler($signal);
    }

    /**
     * Enable/disable asynchronous signal handling or return the old setting
     *
     * @param bool|null $on
     *  - bool Enable or disable.
     *  - null Return old setting.
     *
     * @return bool
     * @since 7.1
     */
    public static function asyncSignal(bool $on = null): bool
    {
        return pcntl_async_signals($on);
    }

    /**************************************************************************************
     * basic process method
     *************************************************************************************/

    /**
     * get current process id
     *
     * @return int
     */
    public static function getPid(): int
    {
        if (function_exists('posix_getpid')) {
            return posix_getpid();
        }

        return getmypid();
    }

    /**
     * get PID by pid File
     *
     * @param string $file
     * @param bool   $checkLive
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
     *
     * ProcessUtil::afterDo(300, function () {
     *      static $i = 0;
     *      echo "#{$i}\talarm\n";
     *      $i++;
     *      if ($i > 20) {
     *          ProcessUtil::clearAlarm(); // close
     *      }
     *  });
     *
     * @param int      $seconds
     * @param callable $handler
     *
     * @return bool|int
     */
    public static function afterDo(int $seconds, callable $handler)
    {
        if (!self::hasPcntl()) {
            return false;
        }

        self::installSignal(Signal::ALRM, $handler);

        return pcntl_alarm($seconds);
    }

    /**
     * @return int
     */
    public static function clearAlarm(): int
    {
        return pcntl_alarm(-1);
    }

    /**
     * Set process title.
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
        if (!$title || 'Darwin' === PHP_OS) {
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

        return false;
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
            throw new RuntimeException("User ({$user}) not found.");
        }

        $uid = (int)$uInfo['uid'];

        // Get gid.
        if ($group) {
            if (!$gInfo = posix_getgrnam($group)) {
                throw new RuntimeException("Group {$group} not exists", -300);
            }

            $gid = (int)$gInfo['gid'];
        } else {
            $gid = (int)$uInfo['gid'];
        }

        if (!posix_initgroups($uInfo['name'], $gid)) {
            throw new RuntimeException("The user [{$user}] is not in the user group ID [GID:{$gid}]", -300);
        }

        posix_setgid($gid);

        if (posix_geteuid() !== $gid) {
            throw new RuntimeException("Unable to change group to {$user} (UID: {$gid}).", -300);
        }

        posix_setuid($uid);

        if (posix_geteuid() !== $uid) {
            throw new RuntimeException("Unable to change user to {$user} (UID: {$uid}).", -300);
        }
    }

    /**
     * @return bool
     */
    public static function hasPcntl(): bool
    {
        return !OS::isWindows() && function_exists('pcntl_fork');
    }

    /**
     * @return bool
     */
    public static function hasPosix(): bool
    {
        return !OS::isWindows() && function_exists('posix_kill');
    }
}
