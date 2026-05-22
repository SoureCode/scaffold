<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

final class InMemoryFeatureFlagsManager extends AbstractFeatureFlagsManager
{
    /**
     * @var Collection<string, FeatureFlagInterface>
     */
    private readonly Collection $collection;

    /**
     * @param array<string, bool> $flags
     */
    public function __construct(array $flags = [])
    {
        $this->collection = new ArrayCollection();

        foreach ($flags as $name => $enabled) {
            $this->setEnabled($name, $enabled);
        }
    }

    public function isEnabled(string $name): bool
    {
        self::validateName($name);

        $flag = $this->collection->get($name);

        return $flag !== null && $flag->isEnabled();
    }

    public function has(string $name): bool
    {
        self::validateName($name);

        return $this->collection->containsKey($name);
    }

    public function enable(string $name): void
    {
        $this->setEnabled($name, true);
    }

    public function disable(string $name): void
    {
        $this->setEnabled($name, false);
    }

    public function remove(string $name): void
    {
        self::validateName($name);

        $this->collection->remove($name);
    }

    public function all(): Collection
    {
        return $this->collection;
    }

    private function setEnabled(string $name, bool $enabled): void
    {
        self::validateName($name);

        $flag = $this->collection->get($name);

        if ($flag === null) {
            $flag = new FeatureFlag();
            $flag->setName($name);
            $this->collection->set($name, $flag);
        }

        $flag->setEnabled($enabled);
    }
}
