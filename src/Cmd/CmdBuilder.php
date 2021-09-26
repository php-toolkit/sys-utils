<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

namespace Toolkit\Sys\Cmd;

use Toolkit\Stdlib\Str;
use function sprintf;

/**
 * class CmdBuilder
 */
class CmdBuilder extends AbstractCmdBuilder
{
    /**
     * @var string
     */
    protected $bin = '';

    /**
     * @var array|string[]
     */
    protected $args = [];

    /**
     * @param string $bin
     * @param string $workDir
     *
     * @return static
     */
    public static function new(string $bin = '', string $workDir = ''): self
    {
        return new static($bin, $workDir);
    }

    /**
     * @param string $subCmd
     * @param string $gitBin
     *
     * @return static
     */
    public static function git(string $subCmd = '', string $gitBin = 'git'): self
    {
        $builder = new static($gitBin, '');

        if ($subCmd) {
            $builder->addArg($subCmd);
        }

        return $builder;
    }

    /**
     * CmdBuilder constructor.
     *
     * @param string $bin
     * @param string $workDir
     */
    public function __construct(string $bin = '', string $workDir = '')
    {
        parent::__construct('', $workDir);

        $this->setBin($bin);
    }

    /**
     * @param string|int $arg
     *
     * @return $this
     */
    public function add($arg): self
    {
        $this->args[] = $arg;
        return $this;
    }

    /**
     * @param string $format
     * @param mixed  ...$a
     *
     * @return $this
     */
    public function addf(string $format, ...$a): self
    {
        $this->args[] = sprintf($format, ...$a);
        return $this;
    }

    /**
     * @param string|int      $arg
     * @param bool|int|string $ifExpr
     *
     * @return $this
     */
    public function addIf($arg, $ifExpr): self
    {
        if ($ifExpr) {
            $this->args[] = $arg;
        }

        return $this;
    }

    /**
     * @param string|int $arg
     *
     * @return $this
     */
    public function addArg($arg): self
    {
        $this->args[] = $arg;
        return $this;
    }

    /**
     * @param ...$args
     *
     * @return $this
     */
    public function addArgs(...$args): self
    {
        if ($args) {
            $this->args = array_merge($this->args, $args);
        }

        return $this;
    }

    /**
     * @param bool $printOutput
     *
     * @return AbstractCmdBuilder
     */
    public function run(bool $printOutput = false): AbstractCmdBuilder
    {
        $this->printOutput = $printOutput;

        $command = $this->buildCommandLine();
        $this->innerExecute($command, $this->workDir);

        return $this;
    }

    /**
     * @return string
     */
    protected function buildCommandLine(): string
    {
        $argList = [];
        foreach ($this->args as $arg) {
            $argList[] = Str::shellQuote((string)$arg);
        }

        $argString = implode(' ', $argList);
        return $this->bin . ' ' . $argString;
    }

    /**
     * @param string $bin
     *
     * @return CmdBuilder
     */
    public function setBin(string $bin): self
    {
        $this->bin = $bin;
        return $this;
    }

    /**
     * @param array|string[] $args
     *
     * @return CmdBuilder
     */
    public function setArgs(array $args): self
    {
        $this->args = $args;
        return $this;
    }
}
