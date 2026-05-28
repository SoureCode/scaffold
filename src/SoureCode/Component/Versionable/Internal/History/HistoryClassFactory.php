<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

/**
 * @internal Ensures the runtime-generated *History class exists in memory and
 *           returns its FQCN. Generates the source through
 *           {@see HistoryClassGenerator}, writes it to the cache dir, and
 *           requires the file. After the first call for a given entity, all
 *           subsequent calls short-circuit.
 */
final class HistoryClassFactory
{
    public function __construct(
        private readonly HistoryClassGenerator $generator,
        private readonly string $cacheDir,
    ) {
    }

    /**
     * @param class-string $originalClass
     */
    public function ensureGenerated(string $originalClass): string
    {
        $historyClass = HistoryClassNamer::historyClassFor($originalClass);

        if (class_exists($historyClass, false)) {
            return $historyClass;
        }

        $file = $this->cacheDir . \DIRECTORY_SEPARATOR . HistoryClassNamer::fileNameFor($originalClass);

        if (!is_dir($this->cacheDir)) {
            if (!@mkdir($this->cacheDir, 0o777, true) && !is_dir($this->cacheDir)) {
                throw new \RuntimeException(\sprintf('Cannot create Versionable cache dir at %s.', $this->cacheDir));
            }
        }

        if (!is_file($file)) {
            file_put_contents($file, $this->generator->generate($originalClass));
        }

        require_once $file;

        if (!class_exists($historyClass, false)) {
            throw new \RuntimeException(\sprintf('Generated *History class %s did not load from %s.', $historyClass, $file));
        }

        return $historyClass;
    }
}
