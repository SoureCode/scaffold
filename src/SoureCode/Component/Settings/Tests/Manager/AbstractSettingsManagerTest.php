<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Manager;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Tests\Fixtures\KeyValidationProbe;

final class AbstractSettingsManagerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validKeyProvider(): iterable
    {
        yield 'single lowercase letter' => ['a'];
        yield 'single digit' => ['0'];
        yield 'dotted segment' => ['site.title'];
        yield 'deep dotted segment' => ['mail.smtp.host'];
        yield 'dashed segment' => ['site-title'];
        yield 'underscored segment' => ['site_title'];
        yield 'alphanumeric' => ['feature123'];
        yield 'mixed dots and dashes' => ['app.theme-light_v2'];
        yield 'digit start' => ['1password'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidKeyProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'uppercase letter' => ['Site'];
        yield 'all uppercase' => ['SITE.TITLE'];
        yield 'leading dot' => ['.site'];
        yield 'leading dash' => ['-site'];
        yield 'leading underscore' => ['_site'];
        yield 'space in middle' => ['site title'];
        yield 'trailing space' => ['site '];
        yield 'exclamation' => ['site!'];
        yield 'slash' => ['site/title'];
        yield 'colon' => ['site:title'];
        yield 'unicode letter' => ['café'];
    }

    #[DataProvider('validKeyProvider')]
    public function testValidKeyDoesNotThrow(string $key): void
    {
        KeyValidationProbe::probe($key);
        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidKeyProvider')]
    public function testInvalidKeyThrows(string $key): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid settings key/');

        KeyValidationProbe::probe($key);
    }
}
