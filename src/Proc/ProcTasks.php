<?php declare(strict_types=1);

namespace Toolkit\Sys\Proc;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use Toolkit\Stdlib\Helper\Assert;
use Toolkit\Stdlib\Num;
use Toolkit\Stdlib\Obj\Traits\AutoConfigTrait;
use Toolkit\Sys\Sys;
use function array_chunk;
use function count;
use function println;
use function time;

/**
 * class ProcManager - ProcTasks TIP: not available.
 *
 * @author inhere
 */
class ProcTasks
{
    use AutoConfigTrait {
        __construct as supper;
    }

    /**
     * task data list
     *
     * @var list<mixed>
     */
    private array $tasks = [];

    /**
     * The task handler, will call on sub-process started.
     *
     * - param#1 is one task data.
     * - param#2 is proc ctx: ['id' => index, 'pid' => int].
     *
     * @var callable(array, array): void
     */
    private $taskHandler;

    /**
     * hooks on run task error.
     * - param#1 is Exception.
     * - param#2 is PID.
     * - return bool. TRUE - stop handle task, FALSE - continue handle next.
     *
     * @var callable(Exception, int): bool
     */
    private $errorHandler;

    /**
     * hooks on before create workers, in parent.
     *
     * - param#1 is parent PID
     * - param#2 is process number
     *
     * @var callable(int, int): void
     */
    private $beforeCreateFn;

    /**
     * hooks on after create workers, in parent.
     *
     * - param#1 is parent PID
     * - param#2 is process info {@see $processes}
     *
     * @var callable(int, array): void
     */
    private $workersCreatedFn;

    /**
     * hooks on each worker process started, in worker.
     * - param is process info
     *
     * @var callable(array{id:int, pid:int}): void
     */
    private $workerStartFn;

    /**
     * Hooks on each worker process exited, in parent.
     * - param#1 is PID.
     * - param#2 is ID(worker index).
     * - param#3 is process info.
     *
     * @var callable(int, int, array{exitCode:int, status: int, exitAt: int}): void
     */
    private $workerExitFn;

    /**
     * Hooks on completed, all worker process exited, in parent.
     *
     * @var callable(): void
     */
    private $workersExitedFn;

    /**
     * @var int
     */
    private int $procNum = 0;

    /**
     * @var int
     */
    private int $masterPid = 0;

    /**
     * @var bool
     */
    private bool $daemon = false;

    /**
     * @var string custom process name
     */
    private string $procName = '';

    /**
     * The childs process info. key is worker pid
     *
     * ```php
     * [
     *  pid => [
     *      id => int,
     *      pid => int,
     *      startAt => int,
     *  ]
     * ]
     * ```
     *
     * @var array<int, array{id: int, pid: int, startTime: int}>
     */
    private array $processes = [];

    /**
     * Class constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->supper($config);

        // will auto get cpu num as proc num
        if ($this->procNum < 1) {
            $this->procNum = Sys::getCpuNum();
        }
    }

    /**
     * @param mixed $task Task data
     *
     * @return $this
     */
    public function addTask(mixed $task): self
    {
        $this->tasks[] = $task;
        return $this;
    }

    /**
     * @param array $tasks
     *
     * @return ProcTasks
     */
    public function addTasks(array $tasks): self
    {
        foreach ($tasks as $task) {
            $this->tasks[] = $task;
        }
        return $this;
    }

    /**
     * @param array $tasks
     *
     * @return ProcTasks
     */
    public function setTasks(array $tasks): self
    {
        $this->tasks = $tasks;
        return $this;
    }

    /**
     * @param callable(mixed, array{id:int, pid:int}): void $taskHandler
     *
     * @return $this
     */
    public function setTaskHandler(callable $taskHandler): self
    {
        $this->taskHandler = $taskHandler;
        return $this;
    }

    /**
     * @return $this
     */
    public function run(): self
    {
        ProcessUtil::assertPcntl();

        $tasks = $this->tasks;
        Assert::intShouldGt0($taskNum = count($tasks), 'tasks can not be empty');

        $chunks  = [];
        $procNum = $this->procNum;

        // check proc num and chunk tasks
        if ($taskNum > $procNum) {
            $chunks = array_chunk($tasks, Num::ceil($taskNum / $procNum));
        } else {
            $procNum = $taskNum;
            foreach ($tasks as $task) {
                $chunks[] = [$task];
            }
        }

        $handler = $this->taskHandler;
        Assert::notNull($handler, 'task handler must be set before run');

        // create sub-processes
        $this->createProcesses($procNum, $chunks);

        // wait child exit.
        ProcessUtil::wait(fn(int $pid, int $eCode, int $status) => $this->handleWorkerExit($pid, $eCode, $status));

        // call hooks
        if ($fn = $this->workersExitedFn) {
            $fn($this->masterPid);
        }

        return $this;
    }

    /**
     * @param int $procNum
     * @param array $chunks
     *
     * @return void
     */
    protected function createProcesses(int $procNum, array $chunks): void
    {
        $this->procNum   = $procNum;
        $this->masterPid = ProcessUtil::getPid();

        // set process name.
        ProcessUtil::setTitle($this->procName);

        // call hooks
        if ($fn = $this->beforeCreateFn) {
            $fn($this->masterPid, $procNum);
        }

        // create processes
        $pidAry = [];
        for ($id = 0; $id < $procNum; $id++) {
            $jobs = $chunks[$id];
            $info = ProcessUtil::fork(fn(int $wPid, int $id) => $this->doRunTasks($jobs, $wPid, $id), null, $id);

            // log
            $pidAry[$info['pid']] = $info;
        }

        $this->processes = $pidAry;

        // call hooks
        if ($fn = $this->workersCreatedFn) {
            $fn($this->masterPid, $pidAry);
        }
    }

    /**
     * Run tasks on a worker process
     *
     * @param array $tasks
     * @param int $pid worker pid
     * @param int $id worker id
     *
     * @return void
     */
    #[NoReturn]
    protected function doRunTasks(array $tasks, int $pid, int $id): void
    {
        // set process name.
        if ($this->procName) {
            ProcessUtil::setTitle($this->procName . ' - worker');
        }

        // call hooks
        if ($fn = $this->workerStartFn) {
            $fn($pid, $id);
        }

        $info = [
            'id'  => $id,
            'pid' => $pid,
        ];

        // exec task handler
        $handler = $this->taskHandler;
        foreach ($tasks as $task) {
            try {
                $handler($task, $info);
            } catch (Throwable $e) {
                if ($this->handleTaskError($e, $pid)) {
                    break;
                }
            }
        }

        // exit worker
        exit(0);
    }

    /**
     * @param Throwable $e
     * @param int $pid
     *
     * @return bool TRUE - stop continue handle. FALSE - continue next.
     */
    protected function handleTaskError(Throwable $e, int $pid): bool
    {
        // call hooks
        if ($fn = $this->errorHandler) {
            $stop = $fn($e, $pid);

            return (bool)$stop;
        }

        println("[PID:$pid] ERROR: handle task -", $e->getMessage());
        return false;
    }

    /**
     * In parent.
     *
     * @param int $pid worker PID
     * @param int $exitCode
     * @param int $status
     *
     * @return void
     */
    protected function handleWorkerExit(int $pid, int $exitCode, int $status): void
    {
        $info = $this->processes[$pid];

        // remove info
        unset($this->processes[$pid]);

        if ($fn = $this->workerExitFn) {
            // add field
            $info['exitAt']   = time();
            $info['status']   = $status;
            $info['exitCode'] = $exitCode;

            $fn($pid, $info['id'], $info);
        }
    }

    /**
     * On before create worker process, in parent.
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function onBeforeCreate(callable $listener): self
    {
        $this->beforeCreateFn = $listener;
        return $this;
    }

    /**
     * On all worker process created, in parent.
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function onWorkersCreated(callable $listener): self
    {
        $this->workersCreatedFn = $listener;
        return $this;
    }

    /**
     * On each worker process started, in worker.
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function onWorkerStart(callable $listener): self
    {
        $this->workerStartFn = $listener;
        return $this;
    }

    /**
     * On each worker process exited, in parent.
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function onWorkerExit(callable $listener): self
    {
        $this->workerExitFn = $listener;
        return $this;
    }

    /**
     * On run task error
     *
     * @param callable(Exception, int): void $errorHandler
     *
     * @return ProcTasks
     */
    public function onTaskError(callable $errorHandler): self
    {
        $this->errorHandler = $errorHandler;
        return $this;
    }

    /**
     * On all worker process exited, in parent.
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function onCompleted(callable $listener): self
    {
        $this->workersExitedFn = $listener;
        return $this;
    }

    /**
     * @return int
     */
    public function getProcNum(): int
    {
        return $this->procNum;
    }

    /**
     * @return int
     */
    public function getTaskNum(): int
    {
        return count($this->tasks);
    }

    /**
     * @param int $procNum
     *
     * @return ProcTasks
     */
    public function setProcNum(int $procNum): self
    {
        Assert::intShouldGt0($procNum);
        $this->procNum = $procNum;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDaemon(): bool
    {
        return $this->daemon;
    }

    /**
     * @param bool $daemon
     *
     * @return ProcTasks
     */
    public function setDaemon(bool $daemon): self
    {
        $this->daemon = $daemon;
        return $this;
    }

    /**
     * @return string
     */
    public function getProcName(): string
    {
        return $this->procName;
    }

    /**
     * @param string $procName
     *
     * @return ProcTasks
     */
    public function setProcName(string $procName): self
    {
        $this->procName = $procName;
        return $this;
    }

    /**
     * @return array
     */
    public function getProcesses(): array
    {
        return $this->processes;
    }

    /**
     * @return int
     */
    public function getMasterPid(): int
    {
        return $this->masterPid;
    }
}
