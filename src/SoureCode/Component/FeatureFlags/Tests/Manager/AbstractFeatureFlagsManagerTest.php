<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Manager;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Tests\Fixtures\NameValidationProbe;

final class AbstractFeatureFlagsManagerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validNameProvider(): iterable
    {
        yield 'single lowercase letter' => ['a'];
        yield 'single digit' => ['0'];
        yield 'dotted segment' => ['rollout.beta'];
        yield 'dashed segment' => ['feature-beta'];
        yield 'underscored segment' => ['feature_beta'];
        yield 'alphanumeric' => ['feature123'];
        yield 'mixed' => ['app.feature-x_v2'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidNameProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'uppercase letter' => ['Beta'];
        yield 'leading dot' => ['.beta'];
        yield 'leading dash' => ['-beta'];
        yield 'leading underscore' => ['_beta'];
        yield 'space in middle' => ['feature beta'];
        yield 'exclamation' => ['feature!'];
        yield 'slash' => ['feature/beta'];
        yield 'unicode letter' => ['férvido'];
    }

    #[DataProvider('validNameProvider')]
    public function testValidNameDoesNotThrow(string $name): void
    {
        NameValidationProbe::probe($name);
        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidNameProvider')]
    public function testInvalidNameThrows(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid feature flag name/');

        NameValidationProbe::probe($name);
    }
}
