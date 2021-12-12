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
use Toolkit\Stdlib\Helper\Assert;
use function array_keys;
use function chdir;
use function fclose;
use function getcwd;
use function proc_open;
use const DIRECTORY_SEPARATOR;

/**
 * Class ProcWrapper
 *
 * @link    https://www.php.net/manual/en/function.proc-open.php
 * @package Inhere\Kite\Common
 */
class ProcWrapper
{
    public const PIPE_IN_INDEX   = 0;
    public const PIPE_OUT_INDEX  = 1;
    public const PIPE_ERR_INDEX  = 2;
    public const PIPE_PASS_INDEX = 3;

    public const PIPE_DESCRIPTORS = [
        0 => ['pipe', 'r'], // stdin - read channel
        1 => ['pipe', 'w'], // stdout - write channel
        2 => ['pipe', 'w'], // stdout - error channel
        3 => ['pipe', 'r'], // stdin - This is the pipe we can feed the password into
    ];

    public const FILE_DESCRIPTORS = [
        ['file', '/dev/tty', 'r'], // read channel
        ['file', '/dev/tty', 'w'], // write channel
        ['file', '/dev/tty', 'w']
    ];

    /**
     * @var string
     */
    private string $command;

    /**
     * @var string
     */
    private string $workDir = '';

    /**
     * @var array
     */
    private array $descriptors;

    /**
     * @var array
     */
    private array $pipes = [];

    /**
     * Set ENV for run command
     *
     * null - use raw ENV info
     *
     * @var array|null
     */
    private ?array $runENV = null;

    /**
     * @var array
     */
    private array $options = [];

    /**
     * Process resource by proc_open()
     *
     * @var resource
     */
    private $process;

    /**
     * @var bool
     */
    private bool $windowsOS;

    // --------------- result ---------------

    /**
     * @var int
     */
    private int $code = 0;

    /**
     * @param string $command
     * @param array  $descriptors
     *
     * @return static
     */
    public static function new(string $command = '', array $descriptors = []): static
    {
        return new static($command, $descriptors);
    }

    /**
     * @param string $command
     * @param string $workDir
     *
     * @return array [$code, $output, $error]
     */
    public static function runCmd(string $command, string $workDir = ''): array
    {
        return ProcCmd::quickExec($command, $workDir);
    }

    /**
     * @param string $editor
     * @param string $filepath
     * @param string $workDir
     *
     * @return array
     */
    public static function editFile(string $editor, string $filepath = '', string $workDir = ''): array
    {
        return static::runEditor($editor, $filepath, $workDir);
    }

    /**
     * @param string $editor eg: vim
     * @param string $filepath
     * @param string $workDir
     *
     * @return array
     * @link https://stackoverflow.com/questions/27064185/open-vim-from-php-like-git?noredirect=1&lq=1
     */
    public static function runEditor(string $editor, string $filepath = '', string $workDir = ''): array
    {
        $descriptors = self::FILE_DESCRIPTORS;

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

        $proc = self::new($command, $descriptors)->run($workDir);

        // $output = $proc->read(1);
        $code = $proc->closeAll();

        return [$code];
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

        $this->windowsOS   = '\\' === DIRECTORY_SEPARATOR;
        $this->descriptors = $descriptors;
    }

    /**
     * Alias of open()
     *
     * @param string $workDir
     *
     * @return $this
     */
    public function run(string $workDir = ''): static
    {
        if ($workDir) {
            $this->workDir = $workDir;
        }

        return $this->open();
    }

    /**
     * @return $this
     */
    public function open(): static
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
     * @param int $index
     *
     * @return false|string
     */
    public function readClose(int $index): false|string
    {
        return $this->read($index);
    }

    /**
     * @param int  $index
     * @param bool $close
     *
     * @return false|string
     */
    public function read(int $index, bool $close = false): bool|string
    {
        $pipe = $this->getPipe($index);

        return ProcFunc::readPipe($pipe, $close);
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
     * get process status
     *
     * @return array{command:string,pid:int,running:bool,signaled:bool,stopped:bool,exitcode:int,termsig:int,stopsig:int}
     */
    public function getStatus(): array
    {
        Assert::notNull($this->process, 'process not start or closed');
        return ProcFunc::getStatus($this->process);
    }

    /**
     * @param int $index
     *
     * @return void
     */
    public function closePipe(int $index): void
    {
        if (isset($this->pipes[$index])) {
            fclose($this->pipes[$index]);
        }
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
    public function closeAll(): int
    {
        $this->closePipes();
        return $this->close();
    }

    /**
     * Close process
     * - Should close all pipes before call close()!
     *
     * @return int return === 0 is success.
     */
    public function close(): int
    {
        // Close all pipes before proc_close! $code === 0 is success.
        $this->code = ProcFunc::close($this->process);

        $this->process = null;
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
     * @return string
     */
    public function getWorkDir(): string
    {
        return $this->workDir;
    }

    /**
     * @param string $workDir
     * @param bool   $notEmpty
     *
     * @return ProcWrapper
     */
    public function setWorkDir(string $workDir, bool $notEmpty = true): self
    {
        if ($notEmpty && !$workDir) {
            return $this;
        }

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
        if ($command) {
            $this->command = $command;
        }

        return $this;
    }

    /**
     * @param int $index
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
     * @return bool
     */
    public function isWindowsOS(): bool
    {
        return $this->windowsOS;
    }
}
