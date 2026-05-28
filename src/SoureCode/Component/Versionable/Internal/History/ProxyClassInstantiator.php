<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

use Doctrine\Instantiator\InstantiatorInterface;

/**
 * @internal Substitutes Doctrine's default entity instantiator on a versioned
 *           ClassMetadata so {@see \Doctrine\ORM\Mapping\ClassMetadata::newInstance()}
 *           returns an instance of the runtime-generated proxy subclass.
 *
 *           Doctrine instantiates entities via two paths — `newLazyGhost()`
 *           on the reflClass (covered by also swapping `$classMetadata->reflClass`)
 *           and `newInstance()` which delegates to the instantiator using
 *           the class-name string. This instantiator handles the second.
 */
final class ProxyClassInstantiator implements InstantiatorInterface
{
    /**
     * @param class-string $proxyClass
     */
    public function __construct(
        private readonly string $proxyClass,
    ) {
    }

    public function instantiate(string $className): object
    {
        return (new \ReflectionClass($this->proxyClass))->newInstanceWithoutConstructor();
    }
}
