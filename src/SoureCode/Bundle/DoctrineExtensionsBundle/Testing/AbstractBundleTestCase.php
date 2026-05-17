<?php

declare(strict_types=1);

namespace SoureCode\Bundle\DoctrineExtensionsBundle\Testing;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base class for bundle smoke tests.
 *
 * Symfony's FrameworkBundle leaks an exception handler on kernel shutdown
 * (see https://github.com/symfony/symfony/issues/63693). PHPUnit 11+ flags
 * that as a risky test. This base pops the leftover handler so the suite
 * can keep failOnRisky enabled.
 */
abstract class AbstractBundleTestCase extends KernelTestCase
{
    protected static function ensureKernelShutdown(): void
    {
        parent::ensureKernelShutdown();

        restore_exception_handler();
    }
}
