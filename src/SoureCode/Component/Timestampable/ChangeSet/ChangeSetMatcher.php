<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\ChangeSet;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\UnitOfWork;
use SoureCode\Component\Timestampable\Metadata\ChangedAtBinding;

final class ChangeSetMatcher
{
    public function matches(ChangedAtBinding $binding, object $entity, UnitOfWork $unitOfWork): bool
    {
        foreach ($binding->fields as $field) {
            if ($this->matchesPath($binding, $field, $entity, $unitOfWork, new \SplObjectStorage())) {
                return true;
            }
        }

        return false;
    }

    public function valueMatches(ChangedAtBinding $binding, mixed $newValue): bool
    {
        if (!$binding->hasValueMatcher()) {
            return true;
        }

        return $this->valuesEqual($newValue, $binding->value);
    }

    /**
     * @param \SplObjectStorage<object, true> $visited
     */
    private function matchesPath(
        ChangedAtBinding $binding,
        string $path,
        object $entity,
        UnitOfWork $unitOfWork,
        \SplObjectStorage $visited,
    ): bool {
        if (isset($visited[$entity])) {
            return false;
        }

        $visited[$entity] = true;

        $changeSet = $unitOfWork->getEntityChangeSet($entity);

        if (array_key_exists($path, $changeSet)) {
            return $this->valueMatches($binding, $changeSet[$path][1] ?? null);
        }

        if (!str_contains($path, '.')) {
            return false;
        }

        [$head, $tail] = explode('.', $path, 2);

        if (array_key_exists($head, $changeSet)) {
            $newRelated = $changeSet[$head][1] ?? null;

            if (is_object($newRelated) && $this->matchesNewlyAssignedRelated($binding, $tail, $newRelated, $unitOfWork, $visited)) {
                return true;
            }
        }

        $reflection = $this->findProperty($entity::class, $head);

        if ($reflection === null) {
            return false;
        }

        $relatedValue = $reflection->getValue($entity);

        if ($relatedValue instanceof Collection) {
            foreach ($relatedValue as $element) {
                if (!is_object($element)) {
                    continue;
                }

                if ($this->matchesPath($binding, $tail, $element, $unitOfWork, clone $visited)) {
                    return true;
                }
            }

            return false;
        }

        if (!is_object($relatedValue)) {
            return false;
        }

        return $this->matchesPath($binding, $tail, $relatedValue, $unitOfWork, $visited);
    }

    /**
     * @param \SplObjectStorage<object, true> $visited
     */
    private function matchesNewlyAssignedRelated(
        ChangedAtBinding $binding,
        string $path,
        object $entity,
        UnitOfWork $unitOfWork,
        \SplObjectStorage $visited,
    ): bool {
        if (!str_contains($path, '.')) {
            $reflection = $this->findProperty($entity::class, $path);

            if ($reflection === null) {
                return false;
            }

            return $this->valueMatches($binding, $reflection->getValue($entity));
        }

        return $this->matchesPath($binding, $path, $entity, $unitOfWork, $visited);
    }

    private function findProperty(string $class, string $name): ?\ReflectionProperty
    {
        $reflection = new \ReflectionClass($class);

        do {
            if ($reflection->hasProperty($name)) {
                return $reflection->getProperty($name);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return null;
    }

    private function valuesEqual(mixed $actual, mixed $expected): bool
    {
        if ($actual === $expected) {
            return true;
        }

        if ($expected instanceof \BackedEnum) {
            if ($actual instanceof \BackedEnum) {
                return false;
            }

            return $actual === $expected->value;
        }

        if ($actual instanceof \BackedEnum) {
            return $actual->value === $expected;
        }

        return false;
    }
}
