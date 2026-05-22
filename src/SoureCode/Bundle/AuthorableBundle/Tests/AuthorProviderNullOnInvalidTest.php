<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests;

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
            'AuthorableBundle must wire AuthorableDeletionMarkerProvider\'s AuthorProviderInterface with nullOnInvalid() so a missing/broken provider does not break boot — the provider treats a null author as "no stamping".',
        );
    }
}
