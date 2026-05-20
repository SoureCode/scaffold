<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Model;

interface SettingInterface
{
    public function getKey(): string;

    public function setKey(string $key): void;

    public function getValue(): mixed;

    public function setValue(mixed $value): void;
}
