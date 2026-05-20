<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RemovableBundle\Tests;

use PHPUnit\Framework\TestCase;

final class AuthorProviderNullOnInvalidTest extends TestCase
{
    public function testAuthorProviderIsWiredWithNullOnInvalid(): void
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__) . '/config/services.php',
        );

        self::assertStringContainsString(
            "service(AuthorProviderInterface::class)->nullOnInvalid()",
            $source,
            'RemovableBundle must wire AuthorProviderInterface with nullOnInvalid() so a missing/broken provider does not break boot — Remover treats a null provider as "no author".',
        );
    }
}
