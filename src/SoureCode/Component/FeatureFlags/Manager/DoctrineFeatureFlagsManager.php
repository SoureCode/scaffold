<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactoryInterface;
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

final class DoctrineFeatureFlagsManager extends AbstractFeatureFlagsManager
{
    /**
     * @var EntityRepository<FeatureFlagInterface>
     */
    private readonly EntityRepository $featureFlagRepository;

    /**
     * @param class-string<FeatureFlagInterface> $featureFlagEntityClassName
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $featureFlagEntityClassName,
        private readonly FeatureFlagFactoryInterface $featureFlagFactory,
    ) {
        $this->featureFlagRepository = $this->entityManager->getRepository($this->featureFlagEntityClassName);
    }

    public function isEnabled(string $name): bool
    {
        self::validateName($name);

        $flag = $this->featureFlagRepository->find($name);

        if ($flag === null) {
            return false;
        }

        return $flag->isEnabled();
    }

    public function has(string $name): bool
    {
        self::validateName($name);

        return $this->featureFlagRepository->find($name) !== null;
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

        $flag = $this->featureFlagRepository->find($name);

        if ($flag === null) {
            return;
        }

        $this->entityManager->remove($flag);
        $this->entityManager->flush();
    }

    public function all(): Collection
    {
        $collection = new ArrayCollection();

        foreach ($this->featureFlagRepository->findAll() as $flag) {
            $collection->set($flag->getName(), $flag);
        }

        return $collection;
    }

    private function setEnabled(string $name, bool $enabled): void
    {
        self::validateName($name);

        $flag = $this->featureFlagRepository->find($name);

        if ($flag === null) {
            $flag = $this->featureFlagFactory->create($name);
            $this->entityManager->persist($flag);
        }

        $flag->setEnabled($enabled);
        $this->entityManager->flush();
    }
}
