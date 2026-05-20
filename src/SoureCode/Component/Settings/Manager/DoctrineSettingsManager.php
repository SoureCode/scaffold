<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use SoureCode\Component\Settings\Factory\SettingFactoryInterface;
use SoureCode\Component\Settings\Model\SettingInterface;

final class DoctrineSettingsManager extends AbstractSettingsManager
{
    /**
     * @var EntityRepository<SettingInterface>
     */
    private readonly EntityRepository $settingRepository;

    /**
     * @param class-string<SettingInterface> $settingEntityClassName
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $settingEntityClassName,
        private readonly SettingFactoryInterface $settingFactory,
    ) {
        $this->settingRepository = $this->entityManager->getRepository($this->settingEntityClassName);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        self::validateKey($key);

        $setting = $this->settingRepository->find($key);

        if ($setting === null) {
            return $default;
        }

        return $setting->getValue();
    }

    public function has(string $key): bool
    {
        self::validateKey($key);

        return $this->settingRepository->find($key) !== null;
    }

    public function set(string $key, mixed $value): void
    {
        self::validateKey($key);

        $setting = $this->settingRepository->find($key);

        if ($setting === null) {
            $setting = $this->settingFactory->create($key);
            $this->entityManager->persist($setting);
        }

        $setting->setValue($value);
        $this->entityManager->flush();
    }

    public function remove(string $key): void
    {
        self::validateKey($key);

        $setting = $this->settingRepository->find($key);

        if ($setting === null) {
            return;
        }

        $this->entityManager->remove($setting);
        $this->entityManager->flush();
    }

    public function all(): Collection
    {
        $collection = new ArrayCollection();

        foreach ($this->settingRepository->findAll() as $setting) {
            $collection->set($setting->getKey(), $setting);
        }

        return $collection;
    }
}
