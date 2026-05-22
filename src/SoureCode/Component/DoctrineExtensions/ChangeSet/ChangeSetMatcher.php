<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\ChangeSet;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\UnitOfWork;
use SoureCode\Component\DoctrineExtensions\Metadata\ChangeBindingInterface;

final class ChangeSetMatcher
{
    /**
     * @var array<class-string, array<string, \ReflectionProperty|null>>
     */
    private array $propertyCache = [];

    /**
     * @template T of object
     *
     * @param T $entity
     */
    public function matches(ChangeBindingInterface $binding, object $entity, UnitOfWork $unitOfWork): bool
    {
        foreach ($binding->getFields() as $field) {
            if ($this->matchesPath($binding, $field, $entity, $unitOfWork, new \SplObjectStorage())) {
                return true;
            }
        }

        return false;
    }

    public function valueMatches(ChangeBindingInterface $binding, mixed $newValue): bool
    {
        if (!$binding->hasValueMatcher()) {
            return true;
        }

        return $this->valuesEqual($newValue, $binding->getValue());
    }

    /**
     * @template T of object
     *
     * @param T $entity
     * @param \SplObjectStorage<object, true> $visited
     */
    private function matchesPath(
        ChangeBindingInterface $binding,
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

                // Share $visited across siblings: cloning per element used
                // to defeat the cycle guard (siblings could walk back into
                // an in-flight relative) and allocated a fresh
                // SplObjectStorage per element.
                if ($this->matchesPath($binding, $tail, $element, $unitOfWork, $visited)) {
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
     * @template T of object
     *
     * @param T $entity
     * @param \SplObjectStorage<object, true> $visited
     */
    private function matchesNewlyAssignedRelated(
        ChangeBindingInterface $binding,
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

    /**
     * Walks the class hierarchy and returns the first `\ReflectionProperty`
     * named `$name`, or `null` if no class in the chain declares it. The
     * result is memoized: `walkPath`/`matchesPath` call this for every
     * binding match inside `onFlush`, so re-running reflection per call
     * adds up fast on hot paths.
     *
     * @param class-string $class
     */
    public function findProperty(string $class, string $name): ?\ReflectionProperty
    {
        if (array_key_exists($name, $this->propertyCache[$class] ?? [])) {
            return $this->propertyCache[$class][$name];
        }

        $reflection = new \ReflectionClass($class);

        do {
            if ($reflection->hasProperty($name)) {
                return $this->propertyCache[$class][$name] = $reflection->getProperty($name);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $this->propertyCache[$class][$name] = null;
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
