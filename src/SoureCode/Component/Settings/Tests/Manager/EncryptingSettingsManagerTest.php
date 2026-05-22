<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Manager\EncryptingSettingsManager;
use SoureCode\Component\Settings\Manager\InMemorySettingsManager;
use SoureCode\Component\Settings\Tests\Support\Base64StubCoder;

final class EncryptingSettingsManagerTest extends TestCase
{
    public function testSensitiveKeyIsEncryptedOnWriteAndDecryptedOnRead(): void
    {
        $inner = new InMemorySettingsManager();
        $manager = new EncryptingSettingsManager($inner, new Base64StubCoder(), ['stripe.secret']);

        $manager->set('stripe.secret', 'sk_live_42');

        $rawInStore = $inner->get('stripe.secret');
        self::assertIsString($rawInStore);
        self::assertStringStartsWith('enc::', $rawInStore, 'sensitive values are tagged in storage');
        self::assertStringNotContainsString('sk_live_42', $rawInStore);

        self::assertSame('sk_live_42', $manager->get('stripe.secret'));
    }

    public function testNonSensitiveKeyPassesThroughUntouched(): void
    {
        $inner = new InMemorySettingsManager();
        $manager = new EncryptingSettingsManager($inner, new Base64StubCoder(), ['stripe.secret']);

        $manager->set('site.title', 'Hello');

        self::assertSame('Hello', $inner->get('site.title'));
        self::assertSame('Hello', $manager->get('site.title'));
    }

    public function testReadOfMissingKeyReturnsDefault(): void
    {
        $inner = new InMemorySettingsManager();
        $manager = new EncryptingSettingsManager($inner, new Base64StubCoder(), ['stripe.secret']);

        self::assertNull($manager->get('stripe.secret'));
        self::assertSame('fallback', $manager->get('stripe.secret', 'fallback'));
    }

    public function testReadOfLegacyPlaintextRowOnSensitiveKeyThrows(): void
    {
        $inner = new InMemorySettingsManager(['stripe.secret' => 'sk_live_legacy_plain']);
        $manager = new EncryptingSettingsManager($inner, new Base64StubCoder(), ['stripe.secret']);

        $this->expectException(\RuntimeException::class);
        $manager->get('stripe.secret');
    }

    public function testRemoveAndAllPassThrough(): void
    {
        $inner = new InMemorySettingsManager();
        $manager = new EncryptingSettingsManager($inner, new Base64StubCoder(), ['stripe.secret']);

        $manager->set('stripe.secret', 'sk_live_42');
        $manager->set('site.title', 'Hi');

        self::assertCount(2, $manager->all());

        $manager->remove('stripe.secret');
        self::assertFalse($manager->has('stripe.secret'));
    }
}
