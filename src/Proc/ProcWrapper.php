<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

namespace Toolkit\Sys\Proc;

use InvalidArgumentException;
use RuntimeException;
use function array_keys;
use function chdir;
use function fclose;
use function getcwd;
use function proc_open;
use function stream_get_contents;
use const DIRECTORY_SEPARATOR;

/**
 * Class ProcFuncWrapper
 *
 * @link    https://www.php.net/manual/en/function.proc-open.php
 * @package Inhere\Kite\Common
 */
class ProcWrapper
{
    /**
     * @var string
     */
    private $command;

    /**
     * @var string|null
     */
    private $workDir;

    /**
     * @var array
     */
    private $descriptors;

    /**
     * @var array
     */
    private $pipes = [];

    /**
     * Set ENV for run command
     *
     * null - use raw ENV info
     *
     * @var array|null
     */
    private $runENV;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var resource
     */
    private $process;

    // --------------- result ---------------

    /**
     * @var int
     */
    private $code = 0;

    /**
     * @var string
     */
    private $error = '';

    /**
     * @var string
     */
    private $output = '';

    /**
     * @param string $command
     * @param array  $descriptors
     *
     * @return static
     */
    public static function new(string $command = '', array $descriptors = []): self
    {
        return new self($command, $descriptors);
    }

    /**
     * @param string $command
     * @param string $workDir
     *
     * @return array [$code, $output, $error]
     */
    public static function runCmd(string $command, string $workDir = ''): array
    {
        $isWindowsOS = '\\' === DIRECTORY_SEPARATOR;
        $descriptors = [
            0 => ['pipe', 'r'], // stdin - read channel
            1 => ['pipe', 'w'], // stdout - write channel
            2 => ['pipe', 'w'], // stdout - error channel
            3 => ['pipe', 'r'], // stdin - This is the pipe we can feed the password into
        ];

        if ($isWindowsOS) {
            unset($descriptors[3]);
        }

        $proc = new ProcWrapper($command, $descriptors);
        $proc->run($workDir);

        $pipes = $proc->getPipes();

        // Nothing to push to input.
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        if (!$isWindowsOS) {
            // TODO: Write passphrase in pipes[3].
            fclose($pipes[3]);
        }

        // Close all pipes before proc_close! $code === 0 is success.
        $code = $proc->close();
        return [$code, $output, $error];
    }

    /**
     * @param string $editor
     * @param string $filepath
     * @param string $workDir
     *
     * @return array
     * @link https://stackoverflow.com/questions/27064185/open-vim-from-php-like-git?noredirect=1&lq=1
     */
    public static function runEditor(string $editor, string $filepath = '', string $workDir = ''): array
    {
        $descriptors = [
            ['file', '/dev/tty', 'r'],
            ['file', '/dev/tty', 'w'],
            ['file', '/dev/tty', 'w']
        ];

        // $process = proc_open("vim $file", $descriptors, $pipes, $workDir);
        // \var_dump(proc_get_status($process));
        // while(true){
        //     if (proc_get_status($process)['running'] === false){
        //         break;
        //     }
        // }
        // \var_dump(proc_get_status($process));

        $command = $editor;
        // eg: 'vim some.file'
        if ($filepath) {
            $command .= ' ' . $filepath;
        }

        $proc = new ProcWrapper($command, $descriptors);
        $proc->run($workDir);

        $code = $proc->close();

        return [$code];
    }

    /**
     * @param $pipe
     *
     * @return string
     */
    public static function readAndClosePipe($pipe): string
    {
        $output = stream_get_contents($pipe);
        fclose($pipe);

        return $output;
    }

    /**
     * Class constructor.
     *
     * @param string $command
     * @param array  $descriptors
     */
    public function __construct(string $command = '', array $descriptors = [])
    {
        $this->command = $command;

        $this->descriptors = $descriptors;
    }

    /**
     * Alias of open()
     *
     * @param string|null $workDir
     *
     * @return $this
     */
    public function run(string $workDir = null): self
    {
        if ($workDir !== null) {
            $this->workDir = $workDir;
        }

        return $this->open();
    }

    /**
     * @return $this
     */
    public function open(): self
    {
        if (!$command = $this->command) {
            throw new InvalidArgumentException('The want execute command is cannot be empty');
        }

        $curDir = '';
        $workDir = $this->workDir ?: null;
        $options = $this->options;

        if ($workDir) {
            $curDir = getcwd();
        }

        $options['suppress_errors'] = true;
        if ('\\' === DIRECTORY_SEPARATOR) { // windows
            $options['bypass_shell'] = true;
        }

        // ERROR on windows
        //  proc_open(): CreateProcess failed, error code - 123
        //
        // https://docs.microsoft.com/zh-cn/windows/win32/debug/system-error-codes--0-499-
        // The filename, directory name, or volume label syntax is incorrect.
        // FIX:
        //  1. runCmd() not set $descriptors[3] on windows
        //  2. $workDir set as null when is empty.
        $process = proc_open($command, $this->descriptors, $this->pipes, $workDir, $this->runENV, $options);

        if (!is_resource($process)) {
            throw new RuntimeException("Can't open resource with proc_open.");
        }

        // fix: revert workdir after run end.
        if ($curDir) {
            chdir($curDir);
        }

        $this->process = $process;
        return $this;
    }

    /**
     * @param int  $index
     * @param bool $close
     *
     * @return false|string
     */
    public function read(int $index, bool $close = true)
    {
        $thePipe = $this->getPipe($index);
        $output  = stream_get_contents($thePipe);

        if ($close) {
            fclose($thePipe);
        }

        return $output;
    }

    /**
     * @param array $indexes
     */
    public function closePipes(array $indexes = []): void
    {
        // empty for close all pipes
        if (!$indexes) {
            $indexes = array_keys($this->pipes);
        }

        foreach ($indexes as $index) {
            if (isset($this->pipes[$index])) {
                fclose($this->pipes[$index]);
            }
        }
    }

    /**
     * @return int
     */
    public function getPid(): int
    {
        $info = $this->getStatus();

        return $info['pid'] ?? 0;
    }

    /**
     * @return array
     *  - command  string
     *  - pid      int
     *  - running  bool
     *  - signaled bool
     *  - stopped  bool
     *  - exitcode int
     */
    public function getStatus(): array
    {
        return ProcFunc::getStatus($this->process);
    }

    /**
     * @return int
     */
    public function close(): int
    {
        // Close all pipes before proc_close! $code === 0 is success.
        $this->code = ProcFunc::close($this->process);

        return $this->code;
    }

    /**
     * Alias of terminate()
     *
     * @return bool
     */
    public function term(): bool
    {
        return ProcFunc::terminate($this->process);
    }

    /**
     * @return bool
     */
    public function terminate(): bool
    {
        return ProcFunc::terminate($this->process);
    }

    /**
     * @return resource
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * @param int $index
     *
     * @return resource
     */
    public function getPipe(int $index)
    {
        if (!isset($this->pipes[$index])) {
            throw new RuntimeException("the pipe is not exist, pos: $index");
        }

        return $this->pipes[$index];
    }

    /**
     * @return array
     */
    public function getPipes(): array
    {
        return $this->pipes;
    }

    /**
     * @return string|null
     */
    public function getWorkDir(): ?string
    {
        return $this->workDir;
    }

    /**
     * @param string $workDir
     *
     * @return ProcWrapper
     */
    public function setWorkDir(string $workDir): self
    {
        $this->workDir = $workDir;
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
     * @param string $command
     *
     * @return ProcWrapper
     */
    public function setCommand(string $command): self
    {
        $this->command = $command;
        return $this;
    }

    /**
     * @param index $index
     * @param array $spec
     *
     * @return ProcWrapper
     */
    public function setDescriptor(int $index, array $spec): self
    {
        $this->descriptors[$index] = $spec;
        return $this;
    }

    /**
     * @param array $descriptors
     *
     * @return ProcWrapper
     */
    public function setDescriptors(array $descriptors): self
    {
        $this->descriptors = $descriptors;
        return $this;
    }

    /**
     * @return array
     */
    public function getRunENV(): array
    {
        return $this->runENV;
    }

    /**
     * @param array $runENV
     */
    public function setRunENV(array $runENV): void
    {
        $this->runENV = $runENV;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return int
     */
    public function getExitCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getOutput(): string
    {
        return $this->output;
    }
}
