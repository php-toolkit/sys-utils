<?php declare(strict_types=1);

namespace Toolkit\Sys\Proc;

use Closure;
use RuntimeException;
use Toolkit\Stdlib\Helper\Assert;
use Toolkit\Stdlib\OS;
use function function_exists;
use function getmypid;
use function pcntl_alarm;
use function pcntl_async_signals;
use function pcntl_fork;
use function pcntl_signal;
use function pcntl_signal_dispatch;
use function pcntl_signal_get_handler;
use function pcntl_sigwaitinfo;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function posix_getpid;
use function posix_setsid;
use function time;
use function usleep;
use const WNOHANG;

/**
 * class PcntlFunc
 *
 * @author inhere
 */
class PcntlFunc
{
    /**
     * @return bool
     */
    public static function hasPcntl(): bool
    {
        return !OS::isWindows() && function_exists('pcntl_fork');
    }

    public static function assertPcntl(): void
    {
        if (!self::hasPcntl()) {
            throw new RuntimeException('the extension "pcntl" is required');
        }
    }

    /**
     * get current process id
     *
     * @return int
     */
    public static function getPid(): int
    {
        return function_exists('posix_getpid') ? posix_getpid() : getmypid();
    }

    /**
     * install signal
     *
     * @param int $signal e.g: SIGTERM SIGINT(Ctrl+C) SIGUSR1 SIGUSR2 SIGHUP
     * @param callable $handler
     *
     * @return bool
     */
    public static function installSignal(int $signal, callable $handler): bool
    {
        return pcntl_signal($signal, $handler, false);
    }

    /**
     * dispatch signal
     *
     * @return bool
     */
    public static function dispatchSignal(): bool
    {
        // receive and dispatch signal
        return pcntl_signal_dispatch();
    }

    /**
     * Get the current handler for specified signal.
     *
     * @param int $signal
     *
     * @return resource|false
     */
    public static function getSignalHandler(int $signal)
    {
        return pcntl_signal_get_handler($signal);
    }

    /**
     * Enable/disable asynchronous signal handling or return the old setting
     *
     * @param bool|null $on bool - Enable or disable; null - Return old setting.
     *
     * @return bool
     */
    public static function asyncSignal(bool $on = null): bool
    {
        return pcntl_async_signals($on);
    }

    /**
     * @return int
     */
    public static function clearAlarm(): int
    {
        return pcntl_alarm(-1);
    }

    /**********************************************************************
     * create child process `pcntl`
     *********************************************************************/

    /**
     * Fork/create a child process.
     *
     * **Example:**
     *
     * ```php
     * $info = ProcessUtil::fork(function(int $pid, int $id) {
     *      // do something...
     *
     *      // exit worker
     *      exit(0);
     * });
     * ```
     *
     * @param callable(int, int):void $onStart Will running on the child process start.
     * @param null|callable(int):void $onForkError Call on fork process error
     * @param int $id The process index number. when use `forks()`
     *
     * @return array{id: int, pid: int, startAt: int}
     */
    public static function fork(callable $onStart, callable $onForkError = null, int $id = 0): array
    {
        $info = [];
        $pid  = pcntl_fork();

        // at parent, get forked child info
        if ($pid > 0) {
            $info = [
                'id'      => $id,
                'pid'     => $pid, // child pid
                'startAt' => time(),
            ];
        } elseif ($pid === 0) {
            // at child
            $workerPid = self::getPid();
            $onStart($workerPid, $id);
        } elseif ($onForkError) {
            $onForkError($pid);
        } else {
            throw new RuntimeException('Fork child process failed!');
        }

        return $info;
    }

    /**
     * Alias of fork()
     *
     * @param callable $onStart
     * @param callable|null $onError
     * @param int $id
     *
     * @return array|false
     * @see ProcessUtil::fork()
     */
    public static function create(callable $onStart, callable $onError = null, int $id = 0): bool|array
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
     * Alias of forks()
     *
     * @param int $number
     * @param callable $onStart
     * @param callable|null $onForkError
     *
     * @return array
     * @see ProcessUtil::forks()
     */
    public static function multi(int $number, callable $onStart, callable $onForkError = null): array
    {
        return self::forks($number, $onStart, $onForkError);
    }

    /**
     * fork/create multi child processes.
     *
     * @param int $number
     * @param callable $onStart Will running on the child processes.
     * @param callable|null $onForkError on fork process error
     *
     * @return array<int, array{id: int, pid: int, startTime: int}>
     * @throws RuntimeException
     */
    public static function forks(int $number, callable $onStart, callable $onForkError = null): array
    {
        Assert::intShouldGt0($number, 'process number', true);

        $pidAry = [];
        for ($id = 0; $id < $number; $id++) {
            $info = self::fork(function () use ($onStart) {
                $onStart();
                exit(0);
            }, $onForkError, $id);

            // log
            $pidAry[$info['pid']] = $info;
        }

        return $pidAry;
    }

    /**
     * Wait all child processes exit.
     *
     * @param callable(int, int, int):void $onChildExit On child process exited callback.
     */
    public static function wait(callable $onChildExit): void
    {
        $status = 0;

        // pid < 0：子进程都没了
        // pid > 0：捕获到一个子进程退出的情况
        // pid = 0：没有捕获到退出的子进程
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) >= 0) {
            if ($pid) {
                // TIP: get signal
                // $singal = pcntl_wifsignaled($status);

                // handler(int $pid, int $exitCode, int $status)
                $onChildExit($pid, pcntl_wexitstatus($status), $status);
            } else {
                // sleep 50ms
                usleep(50000);
            }
        }
    }
}
