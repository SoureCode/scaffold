<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use SoureCode\Component\Versionable\Internal\History\EntityProxyFactory;
use SoureCode\Component\Versionable\Internal\History\HistoryHydrator;
use SoureCode\Component\Versionable\Internal\History\HistoryRegistry;
use SoureCode\Component\Versionable\Internal\History\ProxyClassInstantiator;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Hooks into Doctrine's loadClassMetadata event and swaps the reflection
 * class on every versioned ClassMetadata to the runtime-generated
 * entity-proxy subclass. Doctrine then hydrates and instantiates instances
 * of that subclass, so `$post->get<Field>History()` methods are available
 * on entities returned by `$em->find(...)`, `$repository->find...()` etc.
 *
 * ClassMetadata::$name and the table mapping stay on the original class —
 * only the instantiation class changes — so the identity map and queries
 * keyed by `Post::class` continue to work.
 *
 * Also lazily binds {@see HistoryRegistry} the first time it fires — that
 * way the generated proxy classes can resolve their `get<Field>History()`
 * calls without the bundle needing a boot hook.
 */
final class VersionableClassMetadataListener
{
    private bool $registryBound = false;

    public function __construct(
        private readonly EntityProxyFactory $entityProxyFactory,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly HistoryHydrator $hydrator,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        if (!$this->registryBound) {
            HistoryRegistry::bind($args->getObjectManager(), $this->metadataFactory, $this->hydrator);
            $this->registryBound = true;
        }

        $classMetadata = $args->getClassMetadata();

        $proxyClass = $this->entityProxyFactory->ensureGenerated($classMetadata);

        if ($proxyClass === null) {
            return;
        }

        $classMetadata->reflClass = new \ReflectionClass($proxyClass);

        // Doctrine's `newInstance()` calls the instantiator with the
        // string class name (not the reflClass), so a separate substitution
        // is needed to make full hydration produce subclass instances.
        $instantiatorProperty = new \ReflectionProperty($classMetadata, 'instantiator');
        $instantiatorProperty->setValue($classMetadata, new ProxyClassInstantiator($proxyClass));

        // Alias the proxy class FQCN to the same ClassMetadata so any later
        // `getClassMetadata($proxyClass)` lookup (UnitOfWork uses
        // `$entity::class`, which is the proxy class) returns the user's
        // ClassMetadata instead of trying to load fresh metadata for the
        // proxy — which would fail validation, since the proxy has no
        // `#[ORM\Entity]` attribute.
        $args->getObjectManager()->getMetadataFactory()->setMetadataFor($proxyClass, $classMetadata);
    }
}
