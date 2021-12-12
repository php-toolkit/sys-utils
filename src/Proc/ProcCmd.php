<?php declare(strict_types=1);


namespace Toolkit\Sys\Proc;

use function fclose;
use function fwrite;

/**
 * Class ProcCmd - run command by proc_open
 *
 * @package Toolkit\Sys\Proc
 */
class ProcCmd extends ProcWrapper
{
    private string $input = '';

    // --------------- result ---------------

    /**
     * @var string|null
     */
    private ?string $error = null;

    /**
     * @var string|null
     */
    private ?string $output = null;

    /**
     * @param string $command
     * @param string $workDir
     *
     * @return static
     */
    public static function quickRun(string $command = '', string $workDir = ''): self
    {
        return (new self($command))->run($workDir);
    }

    /**
     * @param string $command
     * @param string $workDir
     *
     * @return array{int,string,string}
     */
    public static function quickExec(string $command = '', string $workDir = ''): array
    {
        $proc = (new self($command))->run($workDir);

        return [
            $proc->getCode(),
            $proc->getOutput(),
            $proc->getError(),
        ];
    }

    /**
     * @return $this
     */
    public function open(): static
    {
        $descriptors = self::PIPE_DESCRIPTORS;

        // on windows
        if ($this->isWindowsOS()) {
            unset($descriptors[3]);
        }

        $this->setDescriptors($descriptors);

        // create process
        parent::open();

        // write contents to input.
        if ($input = $this->input) {
            $this->writeInput($input);
        }

        // on windows
        if (!$this->isWindowsOS()) {
            $this->closePipe(self::PIPE_PASS_INDEX);
        }

        return $this;
    }

    /**
     * @param string $input
     * @param bool   $close Close input pipe
     *
     * @return $this
     */
    public function writeInput(string $input, bool $close = true): static
    {
        fwrite($pipe = $this->getPipe(self::PIPE_IN_INDEX), $input);

        if ($close) {
            fclose($pipe);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function closeInput(): static
    {
        $this->closePipe(self::PIPE_IN_INDEX);
        return $this;
    }

    /**
     * @return array
     */
    public function getResult(): array
    {
        return [
            $this->getCode(),
            $this->getOutput(),
            $this->getError(),
        ];
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        if ($this->error === null) {
            $this->error = $this->readClose(self::PIPE_ERR_INDEX);
        }

        return $this->error;
    }

    /**
     * @return string
     */
    public function getOutput(): string
    {
        if ($this->output === null) {
            $this->output = $this->readClose(self::PIPE_OUT_INDEX);
        }

        return $this->output;
    }

    /**
     * @param string $input
     *
     * @return ProcCmd
     */
    public function setInput(string $input): self
    {
        $this->input = $input;
        return $this;
    }

}