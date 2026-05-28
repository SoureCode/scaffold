<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @internal Ensures the runtime-generated entity-proxy class exists and
 *           returns its FQCN. Mirrors {@see HistoryClassFactory} for the
 *           entity-side proxy used to expose `get<Field>History()` methods
 *           on loaded entities.
 */
final class EntityProxyFactory
{
    public function __construct(
        private readonly EntityProxyGenerator $generator,
        private readonly string $cacheDir,
    ) {
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    public function ensureGenerated(ClassMetadata $classMetadata): ?string
    {
        if (!$this->generator->shouldGenerate($classMetadata)) {
            return null;
        }

        $originalClass = $classMetadata->getName();
        $proxyClass = EntityProxyNamer::proxyClassFor($originalClass);

        if (class_exists($proxyClass, false)) {
            return $proxyClass;
        }

        $file = $this->cacheDir . \DIRECTORY_SEPARATOR . EntityProxyNamer::fileNameFor($originalClass);

        if (!is_dir($this->cacheDir)) {
            if (!@mkdir($this->cacheDir, 0o777, true) && !is_dir($this->cacheDir)) {
                throw new \RuntimeException(\sprintf('Cannot create Versionable cache dir at %s.', $this->cacheDir));
            }
        }

        if (!is_file($file)) {
            file_put_contents($file, $this->generator->generate($classMetadata));
        }

        require_once $file;

        if (!class_exists($proxyClass, false)) {
            throw new \RuntimeException(\sprintf('Generated entity-proxy class %s did not load from %s.', $proxyClass, $file));
        }

        return $proxyClass;
    }
}
