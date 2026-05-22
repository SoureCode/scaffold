<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\Lock;

use PHPUnit\Framework\TestCase;

/**
 * `TracingLockFactory` currently redeclares
 * {@see \Symfony\Component\Lock\LockFactory::$logger} as `readonly`, which
 * produces a PHP fatal "cannot redeclare non-readonly property" the moment
 * the class is autoloaded.
 *
 * Touching the class from inside this PHPUnit process — even via reflection
 * — crashes the whole runner with "Premature end of PHP process". The bug
 * is therefore exposed by shelling out to a child PHP process that
 * attempts the load and reports the fatal back through its exit status.
 *
 * When the underlying bug is fixed, this test starts failing (child exits 0
 * and the warning message disappears); that's the signal to replace this
 * file with real unit tests that instantiate the factory and assert the
 * decorator behaviour.
 */
final class TracingLockFactoryTest extends TestCase
{
    public function testClassCannotBeAutoloadedBecauseItRedeclaresLoggerAsReadonly(): void
    {
        $autoload = dirname(__DIR__, 6) . '/vendor/autoload.php';
        $script = sprintf(
            'require %s; class_exists(%s);',
            var_export($autoload, true),
            var_export(\SoureCode\Bundle\TraceableBundle\Lock\TracingLockFactory::class, true),
        );

        $process = proc_open(
            [\PHP_BINARY, '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        self::assertIsResource($process);

        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertNotSame(0, $exitCode, 'TracingLockFactory should currently fail to autoload');
        self::assertStringContainsString('Cannot redeclare non-readonly property', $stderr);
        self::assertStringContainsString('TracingLockFactory::$logger', $stderr);
    }
}
