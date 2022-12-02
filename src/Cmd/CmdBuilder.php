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
    protected string $bin = '';

    /**
     * @var array|string[]
     */
    protected array $args = [];

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
     * @param int|string $arg
     *
     * @return $this
     */
    public function add(int|string $arg): static
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
    public function addf(string $format, ...$a): static
    {
        $this->args[] = sprintf($format, ...$a);
        return $this;
    }

    /**
     * @param int|string $arg
     * @param bool|int|string $ifExpr
     *
     * @return $this
     */
    public function addIf(int|string $arg, bool|int|string $ifExpr): static
    {
        if ($ifExpr) {
            $this->args[] = $arg;
        }

        return $this;
    }

    /**
     * @param int|string $arg
     *
     * @return $this
     */
    public function addArg(int|string $arg): static
    {
        $this->args[] = $arg;
        return $this;
    }

    /**
     * @param ...$args
     *
     * @return $this
     */
    public function addArgs(...$args): static
    {
        if ($args) {
            $this->args = array_merge($this->args, $args);
        }

        return $this;
    }

    /**
     * Call fn on args is not empty.
     *
     * Usage:
     *
     * ```php
     * $c->withIf(fn($args) => $c->addArgs(...$args), $args)
     * ```
     *
     * @template T
     * @param callable(T): void $fn
     * @param T $args
     *
     * @return $this
     */
    public function withIf(callable $fn, mixed $args): static
    {
        if ($args) {
            $fn($this);
        }
        return $this;
    }

    /**
     * @param bool $printOutput
     *
     * @return static
     */
    public function run(bool $printOutput = false): static
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
        if ($this->cmdline) {
            return $this->cmdline;
        }

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
    public function setBin(string $bin): static
    {
        $this->bin = $bin;
        return $this;
    }

    /**
     * @param array|string[] $args
     *
     * @return CmdBuilder
     */
    public function setArgs(array $args): static
    {
        $this->args = $args;
        return $this;
    }
}
