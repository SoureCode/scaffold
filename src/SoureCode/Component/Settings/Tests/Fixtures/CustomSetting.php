<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Fixtures;

use SoureCode\Component\Settings\Model\SettingInterface;

final class CustomSetting implements SettingInterface
{
    private string $key;
    private mixed $value = null;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
