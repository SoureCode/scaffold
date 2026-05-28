<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\Internal\History\EntityProxyFactory;
use SoureCode\Component\Versionable\Internal\History\HistoryClassFactory;
use SoureCode\Component\Versionable\Internal\History\PhpStormMetaWriter;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Pre-generates the runtime `*History` DTOs and `*VersionableProxy` entity
 * proxies for every `#[Versioned]` entity during cache warmup. Without this
 * the same files are written lazily on the first request that touches each
 * entity — correct, but produces a cold-start cost users typically want
 * absorbed by the build step.
 *
 * The warmer is **optional**: skipping it leaves the lazy fallback in
 * place, so a partial warmup never breaks runtime behavior.
 */
final class VersionableCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly HistoryClassFactory $historyClassFactory,
        private readonly EntityProxyFactory $entityProxyFactory,
        private readonly PhpStormMetaWriter $metaWriter,
        private readonly string $cacheDir,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $doctrineFactory = $this->entityManager->getMetadataFactory();

        foreach ($doctrineFactory->getAllMetadata() as $classMetadata) {
            $className = $classMetadata->getName();

            if (!$this->metadataFactory->isVersionable($className)) {
                continue;
            }

            $this->historyClassFactory->ensureGenerated($className);
            $this->entityProxyFactory->ensureGenerated($classMetadata);
        }

        $this->metaWriter->write($this->cacheDir);

        return [];
    }
}
