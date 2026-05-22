<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SoureCode\Component\FeatureFlags\Event\FeatureFlagRemovedEvent;
use SoureCode\Component\FeatureFlags\Event\FeatureFlagToggledEvent;
use SoureCode\Component\FeatureFlags\Event\NullEventDispatcher;
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
        private readonly EventDispatcherInterface $eventDispatcher = new NullEventDispatcher(),
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

        $this->eventDispatcher->dispatch(new FeatureFlagRemovedEvent($name));
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

        if ($flag !== null) {
            $flag->setEnabled($enabled);
            $this->entityManager->flush();
            $this->eventDispatcher->dispatch(new FeatureFlagToggledEvent($name, $enabled, created: false));

            return;
        }

        // Race: another writer may insert the same primary key between our
        // find() and flush(). Retry once after refreshing — the unique
        // constraint on `name` is the source of truth.
        //
        // Conflict policy: LAST WRITE WINS. When the catch path fires, the
        // racing writer has already committed some value; we re-apply our
        // intended $enabled on top of whatever they wrote. This is a
        // deliberate choice because the typical use case (an admin toggling
        // a flag) wants the most recent intent to stick, not the first one.
        // If you need compare-and-set, query first and skip the write when
        // the existing value already matches.
        $flag = $this->featureFlagFactory->create($name);
        $flag->setEnabled($enabled);
        $this->entityManager->persist($flag);

        try {
            $this->entityManager->flush();
            $this->eventDispatcher->dispatch(new FeatureFlagToggledEvent($name, $enabled, created: true));
        } catch (UniqueConstraintViolationException) {
            $this->entityManager->detach($flag);

            $existing = $this->featureFlagRepository->find($name);

            if ($existing === null) {
                throw new \RuntimeException(\sprintf(
                    'FeatureFlags: race on name "%s" but no row found after retry.',
                    $name,
                ));
            }

            $existing->setEnabled($enabled);
            $this->entityManager->flush();
            $this->eventDispatcher->dispatch(new FeatureFlagToggledEvent($name, $enabled, created: false));
        }
    }
}
