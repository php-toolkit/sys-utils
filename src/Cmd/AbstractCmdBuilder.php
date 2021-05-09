<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

namespace Toolkit\Sys\Cmd;

use RuntimeException;
use Toolkit\Sys\Exec;
use function trim;

/**
 * class AbstractCmdBuilder
 */
abstract class AbstractCmdBuilder
{
    public const PRINT_CMD     = 'printCmd';
    public const PRINT_DRY_RUN = 'dryRun';
    public const PRINT_ERROR   = 'error';

    /**
     * @var string
     */
    protected $command = '';

    /**
     * @var string
     */
    protected $workDir;

    /**
     * @var int
     */
    protected $code = 0;

    /**
     * @var string
     */
    protected $error = '';

    /**
     * @var string
     */
    protected $output = '';

    /**
     * Dry run all commands
     *
     * @var bool
     */
    protected $dryRun = false;

    /**
     * @var bool
     */
    protected $printCmd = true;

    /**
     * Ignore check prevision return code
     *
     * @var bool
     */
    protected $ignoreError = false;

    /**
     * @var bool
     */
    protected $printOutput = false;

    /**
     * Class constructor.
     *
     * @param string $command One or multi commands
     * @param string $workDir
     */
    public function __construct(string $command = '', string $workDir = '')
    {
        $this->command = $command;
        $this->workDir = $workDir;
    }

    /**
     * @param string $workDir
     *
     * @return $this
     */
    public function chDir(string $workDir): self
    {
        return $this->changeDir($workDir);
    }

    /**
     * @param string $workDir
     *
     * @return $this
     */
    public function changeDir(string $workDir): self
    {
        $this->workDir = $workDir;
        return $this;
    }

    /**
     * run and print all output
     */
    public function runAndPrint(): void
    {
        $this->run(true);
    }

    /**
     * Run command
     *
     * @param bool $printOutput
     *
     * @return $this
     */
    abstract public function run(bool $printOutput = false): self;

    /**************************************************************************
     * helper methods
     *************************************************************************/

    /**
     * @param string $command
     * @param string $workDir
     */
    protected function innerExecute(string $command, string $workDir): void
    {
        if (!$command) {
            throw new RuntimeException('The execute command cannot be empty');
        }

        if ($this->printCmd) {
            // Color::println("> {$command}", 'yellow');
            $this->printMessage("> $command", self::PRINT_CMD);
        }

        if ($this->dryRun) {
            $this->output = 'DRY-RUN: Command execute success';
            // Color::println($output, 'cyan');
            $this->printMessage($this->output, self::PRINT_DRY_RUN);
            return;
        }

        if ($this->printOutput) {
            $this->execAndPrint($command, $workDir);
        } else {
            $this->execLogReturn($command, $workDir);
        }
    }

    /**
     * exec and log outputs.
     *
     * @param string $command
     * @param string $workDir
     */
    protected function execLogReturn(string $command, string $workDir): void
    {
        [$code, $output, $error] = Exec::run($command, $workDir);

        // save output
        $this->code   = $code;
        $this->error  = trim($error);
        $this->output = trim($output);
    }

    /**
     * direct print to stdout, not return outputs.
     *
     * @param string $command
     * @param string $workDir
     */
    protected function execAndPrint(string $command, string $workDir): void
    {
        // $lastLine = system($command, $exitCode);
        [$exitCode, $lastLine] = Exec::system($command, $workDir);

        $this->code   = $exitCode;
        $this->output = trim($lastLine);

        if ($exitCode !== 0) {
            $this->error = $this->output;
            $this->printMessage("error code $exitCode:\n" . $lastLine, self::PRINT_ERROR);
        } else {
            echo "\n";
        }
    }

    /**
     * @param string $msg
     * @param string $scene eg: printCmd, dryRun, error
     */
    protected function printMessage(string $msg, string $scene): void
    {
        echo "$msg\n";
    }

    /**************************************************************************
     * getter/setter methods
     *************************************************************************/

    /**
     * @param string $command
     *
     * @return $this
     */
    public function setCommand(string $command): self
    {
        $this->command = $command;
        return $this;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @return string
     */
    public function getWorkDir(): string
    {
        return $this->workDir;
    }

    /**
     * @param string $workDir
     *
     * @return $this
     */
    public function setWorkDir(string $workDir): self
    {
        $this->workDir = $workDir;
        return $this;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return bool
     */
    public function isFail(): bool
    {
        return $this->code !== 0;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->code === 0;
    }

    /**
     * @param bool $trim
     *
     * @return string
     */
    public function getOutput(bool $trim = false): string
    {
        return $trim ? trim($this->output) : $this->output;
    }

    /**
     * @return array
     */
    public function getResult(): array
    {
        return [
            'code'   => $this->code,
            'output' => $this->output,
        ];
    }

    /**
     * @param bool $printCmd
     *
     * @return $this
     */
    public function setPrintCmd(bool $printCmd): self
    {
        $this->printCmd = $printCmd;
        return $this;
    }

    /**
     * @param bool $printOutput
     *
     * @return $this
     */
    public function setPrintOutput(bool $printOutput): self
    {
        $this->printOutput = $printOutput;
        return $this;
    }

    /**
     * @return bool
     */
    public function isIgnoreError(): bool
    {
        return $this->ignoreError;
    }

    /**
     * @param bool $ignoreError
     *
     * @return $this
     */
    public function setIgnoreError(bool $ignoreError): self
    {
        $this->ignoreError = $ignoreError;
        return $this;
    }

    /**
     * @param bool $dryRun
     *
     * @return $this
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }
}