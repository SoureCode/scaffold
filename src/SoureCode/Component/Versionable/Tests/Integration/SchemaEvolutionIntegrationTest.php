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
use SoureCode\Component\Versionable\Tests\Fixtures\EvolutionOneField;
use SoureCode\Component\Versionable\Tests\Fixtures\EvolutionTwoFields;

final class SchemaEvolutionIntegrationTest extends TestCase
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

    public function testVersionTableMirrorsCurrentVersionedFieldSet(): void
    {
        $listener = new VersionableSchemaListener(new VersionableMetadataFactory());

        $schema = new Schema();
        $listener->postGenerateSchema(new GenerateSchemaEventArgs($this->entityManager, $schema));

        $oneVersion = $schema->getTable('versionable_evolution_one_field_version');
        self::assertTrue($oneVersion->hasColumn('title'));
        self::assertFalse($oneVersion->hasColumn('body'));

        $twoVersion = $schema->getTable('versionable_evolution_two_fields_version');
        self::assertTrue($twoVersion->hasColumn('title'));
        self::assertTrue($twoVersion->hasColumn('body'));
    }

    public function testRegeneratingSchemaPicksUpNewlyVersionedFieldOnEveryPass(): void
    {
        // postGenerateSchema is what doctrine:migrations:diff calls against in-memory
        // metadata. Calling it repeatedly must keep reflecting the CURRENT entity shape,
        // not a cached one — otherwise adding a #[Versioned] field wouldn't show up in
        // the next generated migration.
        $listener = new VersionableSchemaListener(new VersionableMetadataFactory());

        $first = new Schema();
        $listener->postGenerateSchema(new GenerateSchemaEventArgs($this->entityManager, $first));

        $second = new Schema();
        $listener->postGenerateSchema(new GenerateSchemaEventArgs($this->entityManager, $second));

        self::assertTrue($second->getTable('versionable_evolution_two_fields_version')->hasColumn('body'));

        $firstColumns = array_map(
            static fn($column): string => $column->getName(),
            $first->getTable('versionable_evolution_two_fields_version')->getColumns(),
        );
        $secondColumns = array_map(
            static fn($column): string => $column->getName(),
            $second->getTable('versionable_evolution_two_fields_version')->getColumns(),
        );

        sort($firstColumns);
        sort($secondColumns);
        self::assertSame(
            $firstColumns,
            $secondColumns,
            'Two calls in a row produce the same column set for the same entity shape (idempotent diff baseline).',
        );
    }
}
