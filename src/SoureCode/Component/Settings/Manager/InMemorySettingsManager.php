<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use SoureCode\Component\Settings\Model\Setting;
use SoureCode\Component\Settings\Model\SettingInterface;

final class InMemorySettingsManager extends AbstractSettingsManager
{
    /**
     * @var Collection<string, SettingInterface>
     */
    private readonly Collection $collection;

    /**
     * @param array<string, mixed> $store
     */
    public function __construct(array $store = [])
    {
        $this->collection = new ArrayCollection();

        foreach ($store as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        self::validateKey($key);

        if (!$this->collection->containsKey($key)) {
            return $default;
        }

        return $this->collection->get($key)->getValue();
    }

    public function has(string $key): bool
    {
        self::validateKey($key);

        return $this->collection->containsKey($key);
    }

    public function set(string $key, mixed $value): void
    {
        self::validateKey($key);

        $setting = $this->collection->get($key);

        if ($setting === null) {
            $setting = new Setting();
            $setting->setKey($key);
            $this->collection->set($key, $setting);
        }

        $setting->setValue($value);
    }

    public function remove(string $key): void
    {
        self::validateKey($key);

        $this->collection->remove($key);
    }

    public function all(): Collection
    {
        return $this->collection;
    }
}
