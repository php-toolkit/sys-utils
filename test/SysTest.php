<?php declare(strict_types=1);

namespace Toolkit\SysTest;

use PHPUnit\Framework\TestCase;
use Toolkit\Sys\Sys;
use function vdump;

/**
 * Class SysTest
 *
 * @package Toolkit\SysTest
 */
class SysTest extends TestCase
{
    public function testGetEnvPaths(): void
    {
        self::assertNotEmpty($paths = Sys::getEnvPaths());

        vdump($paths);
    }

    public function testFindExecutable(): void
    {
        self::assertTrue(Sys::isExecutable('php'));
        self::assertNotEmpty($path = Sys::findExecutable('php'));
        vdump($path);
        self::assertEmpty(Sys::findExecutable('php', ['not-exist-dir']));
        self::assertFalse(Sys::isExecutable('php', ['not-exist-dir']));
    }
}
