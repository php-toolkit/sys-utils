<?php declare(strict_types=1);

namespace Toolkit\Sys\Proc;

use Toolkit\Stdlib\Helper\Assert;
use Toolkit\Stdlib\Obj\Traits\AutoConfigTrait;

/**
 * class ProcManager
 *
 * @author inhere
 */
class ProcManager
{
    use AutoConfigTrait;

    public const ON_WORKER_START = 'workerStart';
    public const ON_WORKER_STOP  = 'workerStop';
    public const ON_WORKER_ERROR = 'workerError';
    public const ON_WORKER_EXIT  = 'workerExit';

    public const BEFORE_START_WORKERS  = 'beforeStartWorkers';

    public const AFTER_START_WORKERS  = 'afterStartWorkers';

    /**
     * hooks on sub-process started.
     * - param#1 is PID. param#2 is index.
     *
     * @var callable(int, int): void
     */
    private $workHandler;

    /**
     * hooks on error
     * - param#1 is PID.
     *
     * @var callable(int): void
     */
    private $onError;

    /**
     * hooks on child exit
     * - param#1 is PID.
     *
     * @var callable(int): void
     */
    private $onExit;

    private int $procNum = 1;

    /**
     * @var bool
     */
    private bool $daemon = false;

    /**
     * @var string custom process name
     */
    private string $procName = '';

    /**
     * @return $this
     */
    public function run(): self
    {
        $handler = $this->workHandler;
        Assert::notNull($handler, 'process: logic handler must be set before run');

        try {
            // set process name.
            if ($this->procName) {
                ProcessUtil::setTitle($this->procName);
            }

            // create processes
            $pidInfo = ProcessUtil::forks($this->procNum, $handler, $this->onError);

            // in parent process.
            Assert::notEmpty($pidInfo, 'create process failed');

            // wait child exit.
            ProcessUtil::wait($this->onExit);
        } catch (\Throwable $e) {

        }

        return $this;
    }

    /**
     * @param callable $workHandler
     *
     * @return $this
     */
    public function setWorkHandler(callable $workHandler): self
    {
        $this->workHandler = $workHandler;
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
     * @param int $procNum
     *
     * @return ProcManager
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
     * @return ProcManager
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
     * @return ProcManager
     */
    public function setProcName(string $procName): self
    {
        $this->procName = $procName;
        return $this;
    }

    /**
     * @param callable $onError
     *
     * @return ProcManager
     */
    public function setOnError(callable $onError): self
    {
        $this->onError = $onError;
        return $this;
    }
}
