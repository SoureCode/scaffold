<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Factory;

use SoureCode\Component\Settings\Model\SettingInterface;

interface SettingFactoryInterface
{
    public function create(string $key): SettingInterface;
}
