<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Factory;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Factory\SettingFactory;
use SoureCode\Component\Settings\Model\Setting;
use SoureCode\Component\Settings\Tests\Fixtures\CustomSetting;

final class SettingFactoryTest extends TestCase
{
    public function testCreateProducesSettingInstanceWithKey(): void
    {
        $factory = new SettingFactory();
        $setting = $factory->create('site.title');

        self::assertInstanceOf(Setting::class, $setting);
        self::assertSame('site.title', $setting->getKey());
        self::assertNull($setting->getValue());
    }

    public function testCreateRespectsConfiguredEntityClass(): void
    {
        $factory = new SettingFactory(CustomSetting::class);
        $setting = $factory->create('feature.x');

        self::assertInstanceOf(CustomSetting::class, $setting);
        self::assertSame('feature.x', $setting->getKey());
    }

    public function testConstructorRejectsClassNotImplementingSettingInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SettingFactory(\stdClass::class);
    }
}
