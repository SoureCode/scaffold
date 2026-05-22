<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use SoureCode\Component\Settings\Model\SettingInterface;

/**
 * Concurrency contract: writes are not race-safe. If two processes call
 * {@see self::set()} for the same key at the same time, one of them will
 * raise `Doctrine\DBAL\Exception\UniqueConstraintViolationException` and
 * Doctrine ORM 3.x will close the underlying EntityManager. Callers that
 * actually need contention support must serialize writes upstream (a
 * queue, a row-level advisory lock, or a retry wrapper that supplies a
 * fresh EntityManager).
 */
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

        $setting = new ($this->settingEntityClassName)();
        $setting->setKey($key);
        $setting->setValue($value);

        $this->entityManager->persist($setting);
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
