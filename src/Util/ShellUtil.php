<?php declare(strict_types=1);

namespace Toolkit\Sys\Util;

use Toolkit\Sys\Exec;
use Toolkit\Sys\SysEnv;
use function basename;
use function exec;
use function getenv;
use function implode;
use function preg_match;
use function preg_replace;
use function sprintf;
use function strpos;

/**
 * Class ShellUtil
 *
 * @package Toolkit\Sys\Util
 */
class ShellUtil
{
    /**
     * @param bool $onlyName
     *
     * @return string
     */
    public static function getName(bool $onlyName): string
    {
        $shell = Exec::getOutput('echo $SHELL');

        // eg: '/bin/bash'
        if ($onlyName && $shell && strpos($shell, '/') !== false) {
            $shell = basename($shell);
        }

        return $shell;
    }

    /**
     * Get shell var value by name.
     *
     * // will run: echo $SHELL
     * eg: ShellUtil::getVarValue('SHELL');
     *
     * @param string $varName
     *
     * @return string
     */
    public static function getVarValue(string $varName): string
    {
        return Exec::getOutput(sprintf('echo $%s', $varName));
    }

    /**
     * get bash is available
     *
     * @param string $name
     *
     * @return bool
     */
    public static function shellIsOk(string $name = 'bash'): bool
    {
        // $checkCmd = "/usr/bin/env bash -c 'echo OK'";
        // $shell = 'echo $0';
        $checkCmd = "$name -c 'echo OK'";

        return Exec::auto($checkCmd, false) === 'OK';
    }

    /**
     * get bash is available
     *
     * @return bool
     */
    public static function shIsAvailable(): bool
    {
        return self::shellIsOk('sh');
    }

    /**
     * get bash is available
     *
     * @return bool
     */
    public static function bashIsAvailable(): bool
    {
        return self::shellIsOk();
    }

    /**
     * get screen size
     *
     * ```php
     * list($width, $height) = ShellUtil::getScreenSize();
     * ```
     *
     * @from Yii2
     *
     * @param boolean $refresh whether to force checking and not re-use cached size value.
     *                         This is useful to detect changing window size while the application is running but may
     *                         not get up to date values on every terminal.
     *
     * @return array|boolean An array of ($width, $height) or false when it was not able to determine size.
     */
    public static function getScreenSize(bool $refresh = false)
    {
        static $size;
        if ($size !== null && !$refresh) {
            return $size;
        }

        if (self::shIsAvailable()) {
            // try stty if available
            $stty = [];

            if (exec('stty -a 2>&1', $stty) && preg_match(
                    '/rows\s+(\d+);\s*columns\s+(\d+);/mi',
                    implode(' ', $stty),
                    $matches
                )
            ) {
                return ($size = [$matches[2], $matches[1]]);
            }

            // fallback to tput, which may not be updated on terminal resize
            if (($width = (int)exec('tput cols 2>&1')) > 0 && ($height = (int)exec('tput lines 2>&1')) > 0) {
                return ($size = [$width, $height]);
            }

            // fallback to ENV variables, which may not be updated on terminal resize
            if (($width = (int)getenv('COLUMNS')) > 0 && ($height = (int)getenv('LINES')) > 0) {
                return ($size = [$width, $height]);
            }
        }

        if (SysEnv::isWindows()) {
            $output = [];
            exec('mode con', $output);

            if (isset($output[1]) && strpos($output[1], 'CON') !== false) {
                return ($size = [
                    (int)preg_replace('~\D~', '', $output[3]),
                    (int)preg_replace('~\D~', '', $output[4])
                ]);
            }
        }

        return ($size = false);
    }

}
