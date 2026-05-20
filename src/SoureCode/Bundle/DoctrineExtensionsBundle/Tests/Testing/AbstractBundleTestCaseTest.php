<?php

declare(strict_types=1);

namespace SoureCode\Bundle\DoctrineExtensionsBundle\Tests\Testing;

use PHPUnit\Framework\TestCase;

final class AbstractBundleTestCaseTest extends TestCase
{
    public function testEnsureKernelShutdownAddsAnExtraRestoreExceptionHandlerCall(): void
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/Testing/AbstractBundleTestCase.php',
        );

        self::assertStringContainsString(
            'restore_exception_handler()',
            $source,
            'AbstractBundleTestCase::ensureKernelShutdown must call restore_exception_handler() to pop the handler symfony/framework-bundle leaks on shutdown (see https://github.com/symfony/symfony/issues/63693).',
        );
        self::assertMatchesRegularExpression(
            '/parent::ensureKernelShutdown\(\);\s+restore_exception_handler\(\);/s',
            $source,
            'The restore must run AFTER parent::ensureKernelShutdown so the parent has already popped its own state.',
        );
    }
}
