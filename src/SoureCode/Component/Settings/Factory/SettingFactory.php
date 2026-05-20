<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Factory;

use SoureCode\Component\Settings\Model\Setting;
use SoureCode\Component\Settings\Model\SettingInterface;

final class SettingFactory implements SettingFactoryInterface
{
    /**
     * @param class-string<SettingInterface> $entityClass
     */
    public function __construct(
        private readonly string $entityClass = Setting::class,
    ) {
        if (!is_a($this->entityClass, SettingInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'SettingFactory entity class "%s" must implement %s.',
                $this->entityClass,
                SettingInterface::class,
            ));
        }
    }

    public function create(string $key): SettingInterface
    {
        $setting = new ($this->entityClass)();
        $setting->setKey($key);

        return $setting;
    }
}
