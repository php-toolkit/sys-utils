<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

use Toolkit\Sys\Exec;

require dirname(__DIR__) . '/test/bootstrap.php';

// run: php example/proc-fopen.php
$result = Exec::pexec('ls -al');

vdump($result);
