<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\VersionableBundle\Command\VersionableGenerateCommand;
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
use Symfony\Component\Console\Tester\CommandTester;

final class VersionableGenerateCommandTest extends TestCase
{
    private string $cacheDir;
    private VersionableGenerateCommand $command;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'versionable-cli-' . uniqid('', true);

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 3) . '/Component/Versionable/Tests/Pin/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $entityManager = new EntityManager($connection, $config);

        $factory = new VersionableMetadataFactory($entityManager);
        $metaWriter = new PhpStormMetaWriter();
        $historyClassFactory = new HistoryClassFactory(
            new HistoryClassGenerator($factory, $entityManager),
            $this->cacheDir,
            $metaWriter,
        );
        $entityProxyFactory = new EntityProxyFactory(
            new EntityProxyGenerator($factory),
            $this->cacheDir,
            $metaWriter,
        );

        $this->command = new VersionableGenerateCommand(
            $entityManager,
            $factory,
            $historyClassFactory,
            $entityProxyFactory,
            $metaWriter,
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

    public function testGenerateProducesAllArtifactsAndExitsSuccess(): void
    {
        $tester = new CommandTester($this->command);
        $exit = $tester->execute([]);

        self::assertSame(0, $exit);

        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . HistoryClassNamer::fileNameFor(Pinned::class));
        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . HistoryClassNamer::fileNameFor(Target::class));
        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . EntityProxyNamer::fileNameFor(Pinned::class));
        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . PhpStormMetaWriter::FILE_NAME);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Generated 2 *History', $display);
        self::assertStringContainsString('1 *VersionableProxy', $display);
    }

    public function testClearOptionRemovesPreviousFilesBeforeRegenerating(): void
    {
        // First run: write the files.
        (new CommandTester($this->command))->execute([]);
        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . PhpStormMetaWriter::FILE_NAME);

        // Plant a leftover that should NOT be touched.
        $unrelated = $this->cacheDir . \DIRECTORY_SEPARATOR . 'unrelated.txt';
        file_put_contents($unrelated, 'keep me');

        // Second run with --clear: regenerates everything; leftover stays.
        $tester = new CommandTester($this->command);
        $tester->execute(['--clear' => true]);

        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . HistoryClassNamer::fileNameFor(Pinned::class));
        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . EntityProxyNamer::fileNameFor(Pinned::class));
        self::assertFileExists($this->cacheDir . \DIRECTORY_SEPARATOR . PhpStormMetaWriter::FILE_NAME);
        self::assertFileExists($unrelated, 'unrelated files are not removed by --clear');

        self::assertStringContainsString('--clear', $tester->getDisplay());
    }

    public function testPhpstormMetaContainsOverridesForEveryVersionedEntity(): void
    {
        (new CommandTester($this->command))->execute([]);

        $meta = (string) file_get_contents($this->cacheDir . \DIRECTORY_SEPARATOR . PhpStormMetaWriter::FILE_NAME);

        self::assertStringContainsString('namespace PHPSTORM_META', $meta);
        self::assertStringContainsString('findByVersion', $meta);
        self::assertStringContainsString('findLatestVersion', $meta);
        self::assertStringContainsString('findHistory', $meta);
        self::assertStringContainsString('historyClassFor', $meta);
        self::assertStringContainsString(Pinned::class, $meta);
        self::assertStringContainsString(Target::class, $meta);
        self::assertStringContainsString(HistoryClassNamer::historyClassFor(Pinned::class), $meta);
        self::assertStringContainsString(HistoryClassNamer::historyClassFor(Target::class), $meta);
    }
}
