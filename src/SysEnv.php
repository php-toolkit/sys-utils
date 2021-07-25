<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

namespace Toolkit\Sys;

use Toolkit\Stdlib\OS;
use function defined;
use function explode;
use function getenv;
use function putenv;

/**
 * Class EnvHelper
 *
 * @package Toolkit\Sys\Proc
 */
class SysEnv extends OS
{
    /**
     * @return bool
     */
    public static function supportColor(): bool
    {
        return self::isSupportColor();
    }

    /**
     * Returns true if STDOUT supports colorization.
     * This code has been copied and adapted from
     * \Symfony\Component\Console\Output\OutputStream.
     *
     * @return boolean
     */
    public static function isSupportColor(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return '10.0.10586' === PHP_WINDOWS_VERSION_MAJOR . '.' . PHP_WINDOWS_VERSION_MINOR . '.' . PHP_WINDOWS_VERSION_BUILD || false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI') || 'xterm' === getenv('TERM')// || 'cygwin' === getenv('TERM')
                ;
        }

        if (!defined('STDOUT')) {
            return false;
        }

        return self::isInteractive(STDOUT);
    }

    /**
     * @param string $key
     * @param string $default
     *
     * @return string
     */
    public static function getEnv(string $key, string $default = ''): string
    {
        return getenv($key) ?: $default;
    }

    /**
     * @param string     $key
     * @param string|int $value
     *
     * @return bool
     */
    public static function setEnv(string $key, $value): bool
    {
        return putenv($key . '=' . $value);
    }

    /**
     * @return array
     */
    public static function getEnvPaths(): array
    {
        $pathStr = $_SERVER['PATH'] ?? '';
        if (!$pathStr) {
            return [];
        }

        $sepChar = self::isWindows() ? ';' : ':';
        return explode($sepChar, $pathStr);
    }
}
