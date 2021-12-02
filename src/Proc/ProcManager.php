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

    /**
     * @var callable
     */
    private $handler;

    private int $procNum = 1;

    /**
     * @var string custom process name
     */
    private string $procName = '';

    public function run(): self
    {
        Assert::notNull($this->handler, 'process: logic handler must be set before run');
        $handler = $this->handler;


        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function setHandler(callable $handler): self
    {
        $this->handler = $handler;
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
}
