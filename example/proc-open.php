<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

use Toolkit\Sys\Proc\ProcCmd;

require dirname(__DIR__) . '/test/bootstrap.php';

// run: php example/proc-open.php
$proc = ProcCmd::new('ls -al')->run();

vdump($proc->getResult());

// $proc = ProcWrap::new('ls -al')->run();
// vdump($proc->getStatus());
// $proc->closeAll();

// vdump($proc->getStatus()); // will ex
