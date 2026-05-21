<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

    public function getMany(array $keys): array
    {
        $result = [];

        if ($keys === []) {
            return $result;
        }

        foreach ($keys as $key) {
            self::validateKey($key);
            $result[$key] = null;
        }

        foreach ($this->settingRepository->findBy(['key' => array_values(array_unique($keys))]) as $setting) {
            $result[$setting->getKey()] = $setting->getValue();
        }

        return $result;
    }

    public function set(string $key, mixed $value): void
    {
        self::validateKey($key);

        $setting = $this->settingRepository->find($key);

        if ($setting !== null) {
            $setting->setValue($value);
            $this->entityManager->flush();

            return;
        }

        // Race: another writer may insert the same primary key between our
        // find() and flush(). Retry once after refreshing — the unique
        // constraint on `key` is the source of truth.
        $setting = $this->settingFactory->create($key);
        $setting->setValue($value);
        $this->entityManager->persist($setting);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->entityManager->detach($setting);

            $existing = $this->settingRepository->find($key);

            if ($existing === null) {
                throw new \RuntimeException(\sprintf(
                    'Settings: race on key "%s" but no row found after retry.',
                    $key,
                ));
            }

            $existing->setValue($value);
            $this->entityManager->flush();
        }
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
