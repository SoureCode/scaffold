<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Fixtures\PostStatus;
use SoureCode\Component\Versionable\Tests\Fixtures\PostWithOptions;

final class SchemaColumnOptionsIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);
    }

    public function testSchemaListenerCopiesLengthPrecisionScaleAndEnumOptions(): void
    {
        $schema = new Schema();
        $listener = new VersionableSchemaListener(new VersionableMetadataFactory());

        $listener->postGenerateSchema(new GenerateSchemaEventArgs($this->entityManager, $schema));

        self::assertTrue($schema->hasTable('versionable_post_with_options_version'));
        $versionTable = $schema->getTable('versionable_post_with_options_version');

        $titleColumn = $versionTable->getColumn('title');
        self::assertSame(64, $titleColumn->getLength(), 'length option copied');

        $amountColumn = $versionTable->getColumn('amount');
        self::assertSame(12, $amountColumn->getPrecision(), 'precision option copied');
        self::assertSame(4, $amountColumn->getScale(), 'scale option copied');

        $statusColumn = $versionTable->getColumn('status');
        $platformOptions = $statusColumn->getPlatformOptions();
        self::assertArrayHasKey('enumType', $platformOptions);
        self::assertSame(PostStatus::class, $platformOptions['enumType']);
    }
}
