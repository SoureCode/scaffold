<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable;

use PHPUnit\Framework\TestCase;

final class AuthorProviderNullOnInvalidTest extends TestCase
{
    public function testAuthorProviderIsWiredWithNullOnInvalid(): void
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/config/services_authorable.php',
        );

        self::assertStringContainsString(
            "service(AuthorProviderInterface::class)->nullOnInvalid()",
            $source,
            'LifecycleBundle must wire AuthorableDeletionMarkerProvider\'s AuthorProviderInterface with nullOnInvalid() so a missing/broken provider does not break boot — the provider treats a null author as "no stamping".',
        );
    }
}
