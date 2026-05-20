<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Model;

class Setting implements SettingInterface
{
    protected string $key;
    protected mixed $value = null;

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
