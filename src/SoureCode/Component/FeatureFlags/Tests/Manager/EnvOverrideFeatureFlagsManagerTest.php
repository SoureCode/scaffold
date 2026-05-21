<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Manager;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Manager\EnvOverrideFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Manager\InMemoryFeatureFlagsManager;

final class EnvOverrideFeatureFlagsManagerTest extends TestCase
{
    private const string ENV_NAME = 'FEATURE_BILLING_BETA_RATES';

    protected function tearDown(): void
    {
        putenv(self::ENV_NAME);
    }

    public function testIsEnabledFallsThroughWhenEnvIsUnset(): void
    {
        $inner = new InMemoryFeatureFlagsManager(['billing.beta-rates' => true]);
        $manager = new EnvOverrideFeatureFlagsManager($inner);

        self::assertTrue($manager->isEnabled('billing.beta-rates'));
    }

    #[DataProvider('truthyEnvValues')]
    public function testIsEnabledRespectsTruthyEnvOverride(string $envValue): void
    {
        $inner = new InMemoryFeatureFlagsManager(['billing.beta-rates' => false]);
        $manager = new EnvOverrideFeatureFlagsManager($inner);

        putenv(self::ENV_NAME . '=' . $envValue);

        self::assertTrue($manager->isEnabled('billing.beta-rates'));
    }

    #[DataProvider('falsyEnvValues')]
    public function testIsEnabledRespectsFalsyEnvOverride(string $envValue): void
    {
        $inner = new InMemoryFeatureFlagsManager(['billing.beta-rates' => true]);
        $manager = new EnvOverrideFeatureFlagsManager($inner);

        putenv(self::ENV_NAME . '=' . $envValue);

        self::assertFalse($manager->isEnabled('billing.beta-rates'));
    }

    public function testWriteOperationsDelegateToInner(): void
    {
        $inner = new InMemoryFeatureFlagsManager();
        $manager = new EnvOverrideFeatureFlagsManager($inner);

        $manager->enable('checkout.v2');
        self::assertTrue($inner->isEnabled('checkout.v2'));

        $manager->disable('checkout.v2');
        self::assertFalse($inner->isEnabled('checkout.v2'));

        $manager->remove('checkout.v2');
        self::assertFalse($inner->has('checkout.v2'));
    }

    public function testDisabledDecoratorSkipsEnvAndDelegatesEveryReadToInner(): void
    {
        $inner = new InMemoryFeatureFlagsManager(['billing.beta-rates' => true]);
        $manager = new EnvOverrideFeatureFlagsManager($inner, 'FEATURE_', enabled: false);

        putenv(self::ENV_NAME . '=0');

        self::assertTrue(
            $manager->isEnabled('billing.beta-rates'),
            'when env_override.enabled=false the decorator must ignore env vars and return the inner value',
        );
    }

    public function testEnabledDecoratorReadsBothSources(): void
    {
        $inner = new InMemoryFeatureFlagsManager(['only.in.doctrine' => true]);
        $manager = new EnvOverrideFeatureFlagsManager($inner, 'FEATURE_', enabled: true);

        // Doctrine-only flag falls through cleanly.
        self::assertTrue($manager->isEnabled('only.in.doctrine'));

        // Env-only flag is honoured even though Doctrine has no row for it.
        putenv('FEATURE_ONLY_IN_ENV=1');
        try {
            self::assertTrue($manager->isEnabled('only.in.env'));
        } finally {
            putenv('FEATURE_ONLY_IN_ENV');
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function truthyEnvValues(): iterable
    {
        yield '1' => ['1'];
        yield 'true' => ['true'];
        yield 'TRUE' => ['TRUE'];
        yield 'on' => ['on'];
        yield 'yes' => ['yes'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function falsyEnvValues(): iterable
    {
        yield '0' => ['0'];
        yield 'false' => ['false'];
        yield 'FALSE' => ['FALSE'];
        yield 'off' => ['off'];
        yield 'no' => ['no'];
    }
}
