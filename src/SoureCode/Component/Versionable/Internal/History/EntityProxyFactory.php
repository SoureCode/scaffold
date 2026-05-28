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
        private readonly PhpStormMetaWriter $metaWriter = new PhpStormMetaWriter(),
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
        $file = $this->cacheDir . \DIRECTORY_SEPARATOR . EntityProxyNamer::fileNameFor($originalClass);

        if (!is_dir($this->cacheDir)) {
            if (!@mkdir($this->cacheDir, 0o777, true) && !is_dir($this->cacheDir)) {
                throw new \RuntimeException(\sprintf('Cannot create Versionable cache dir at %s.', $this->cacheDir));
            }
        }

        // File and class membership are independent: the class may already
        // be loaded in the current process (warmer + runtime in the same
        // PHPUnit run, or two requests served by the same worker) while
        // the file on disk is missing (cache cleared, fresh deploy). Both
        // checks must be independent so warmup always ends with both the
        // file written and the class loaded.
        $wrote = false;

        if (!is_file($file)) {
            file_put_contents($file, $this->generator->generate($classMetadata));
            $wrote = true;
        }

        if (!class_exists($proxyClass, false)) {
            require_once $file;
        }

        if (!class_exists($proxyClass, false)) {
            throw new \RuntimeException(\sprintf('Generated entity-proxy class %s did not load from %s.', $proxyClass, $file));
        }

        if ($wrote) {
            $this->metaWriter->write($this->cacheDir);
        }

        return $proxyClass;
    }
}
