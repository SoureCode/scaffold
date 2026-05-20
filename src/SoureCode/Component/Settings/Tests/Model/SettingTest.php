<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Model;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Model\Setting;

final class SettingTest extends TestCase
{
    public function testKeyRoundTrip(): void
    {
        $setting = new Setting();
        $setting->setKey('site.title');

        self::assertSame('site.title', $setting->getKey());
    }

    public function testValueDefaultsToNull(): void
    {
        $setting = new Setting();

        self::assertNull($setting->getValue());
    }

    public function testScalarValueRoundTrip(): void
    {
        $setting = new Setting();
        $setting->setValue('Hello');

        self::assertSame('Hello', $setting->getValue());
    }

    public function testArrayValueRoundTrip(): void
    {
        $setting = new Setting();
        $value = ['nested' => ['list' => [1, 2, 3]]];
        $setting->setValue($value);

        self::assertSame($value, $setting->getValue());
    }

    public function testNullValueCanBeStoredExplicitly(): void
    {
        $setting = new Setting();
        $setting->setValue('something');
        $setting->setValue(null);

        self::assertNull($setting->getValue());
    }
}
