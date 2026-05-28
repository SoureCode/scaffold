<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\VersionableBundle\CacheWarmer\VersionableCacheWarmer;
use SoureCode\Component\Versionable\Internal\History\EntityProxyFactory;
use SoureCode\Component\Versionable\Internal\History\EntityProxyGenerator;
use SoureCode\Component\Versionable\Internal\History\EntityProxyNamer;
use SoureCode\Component\Versionable\Internal\History\HistoryClassFactory;
use SoureCode\Component\Versionable\Internal\History\HistoryClassGenerator;
use SoureCode\Component\Versionable\Internal\History\HistoryClassNamer;
use SoureCode\Component\Versionable\Internal\History\PhpStormMetaWriter;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Pin\Fixtures\Pinned;
use SoureCode\Component\Versionable\Tests\Pin\Fixtures\Target;

final class CacheWarmerTest extends TestCase
{
    private string $cacheDir;
    private EntityManager $entityManager;
    private VersionableCacheWarmer $warmer;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'versionable-warmer-' . uniqid('', true);

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 3) . '/Component/Versionable/Tests/Pin/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        $factory = new VersionableMetadataFactory($this->entityManager);
        $historyClassFactory = new HistoryClassFactory(
            new HistoryClassGenerator($factory, $this->entityManager),
            $this->cacheDir,
        );
        $entityProxyFactory = new EntityProxyFactory(
            new EntityProxyGenerator($factory),
            $this->cacheDir,
        );

        $this->warmer = new VersionableCacheWarmer(
            $this->entityManager,
            $factory,
            $historyClassFactory,
            $entityProxyFactory,
            new PhpStormMetaWriter(),
            $this->cacheDir,
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->cacheDir);
        }
    }

    public function testWarmerIsOptional(): void
    {
        self::assertTrue($this->warmer->isOptional(), 'cache warmup may be skipped without breaking runtime');
    }

    public function testWarmupGeneratesHistoryFilesForEveryVersionedEntity(): void
    {
        self::assertSame([], $this->warmer->warmUp($this->cacheDir));

        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . HistoryClassNamer::fileNameFor(Pinned::class));
        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . HistoryClassNamer::fileNameFor(Target::class));
    }

    public function testWarmupGeneratesEntityProxyOnlyForEntitiesWithVersionedRelations(): void
    {
        $this->warmer->warmUp($this->cacheDir);

        // Pinned has a versioned n:1 to Target → proxy expected.
        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . EntityProxyNamer::fileNameFor(Pinned::class));

        // Target has only a 1:n inverse collection — no owning versioned
        // single-valued association, so no proxy is generated.
        self::assertFileDoesNotExist($this->cacheDir . \DIRECTORY_SEPARATOR . EntityProxyNamer::fileNameFor(Target::class));
    }
}
