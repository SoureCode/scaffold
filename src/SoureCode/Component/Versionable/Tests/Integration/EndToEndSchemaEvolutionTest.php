<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Drives the full Doctrine Migrations flow for every kind of schema change on a versioned entity.
 *
 * For each scenario we:
 *   1. Boot a "before" EntityManager with one fixture directory; create the schema in SQLite.
 *   2. Boot an "after" EntityManager (same SQLite connection) with a sibling fixture directory.
 *      Both fixture sets map to the same table name, so Doctrine treats the second as an
 *      evolution of the first.
 *   3. Call SchemaTool::getUpdateSchemaSql against the "after" EM. The emitted statements
 *      are exactly what `doctrine:migrations:diff` would produce.
 *
 * Each test asserts that the SQL produced contains a fragment proving the change was picked
 * up end-to-end through Doctrine's schema diffing.
 */
final class EndToEndSchemaEvolutionTest extends TestCase
{
    public function testAddingVersionedScalarFieldProducesAddColumnOnVersionTable(): void
    {
        $sql = $this->emitUpgradeSql(
            beforePath: __DIR__ . '/../Fixtures/Evolution/AddScalar/Before',
            afterPath: __DIR__ . '/../Fixtures/Evolution/AddScalar/After',
        );

        self::assertSqlContains($sql, ['ADD', 'evolution_article_version', 'body']);
    }

    public function testRemovingVersionedScalarFieldProducesDropColumnOnVersionTable(): void
    {
        $sql = $this->emitUpgradeSql(
            beforePath: __DIR__ . '/../Fixtures/Evolution/RemoveScalar/Before',
            afterPath: __DIR__ . '/../Fixtures/Evolution/RemoveScalar/After',
        );

        // SQLite emulates DROP COLUMN by rewriting the table; the diff manifests as
        // a CREATE TEMPORARY TABLE ... SELECT without the removed column, then a swap.
        self::assertSqlContains($sql, ['evolution_article_version']);
        self::assertSqlDoesNotMention(
            $sql,
            'body',
            'After dropping the body column, no statement should keep referencing it as a destination column.',
            scope: 'after_swap',
        );
    }

    public function testChangingColumnLengthProducesAlterTableOnVersionTable(): void
    {
        $sql = $this->emitUpgradeSql(
            beforePath: __DIR__ . '/../Fixtures/Evolution/ChangeLength/Before',
            afterPath: __DIR__ . '/../Fixtures/Evolution/ChangeLength/After',
        );

        // SQLite rewrites the table for a column-type change; the rewritten table column
        // for title carries the new length (VARCHAR(255)).
        self::assertSqlContains($sql, ['evolution_article_version', 'VARCHAR(255)']);
    }

    public function testAddingVersionedSingleAssociationProducesAddFieldIdColumn(): void
    {
        $sql = $this->emitUpgradeSql(
            beforePath: __DIR__ . '/../Fixtures/Evolution/AddSingle/Before',
            afterPath: __DIR__ . '/../Fixtures/Evolution/AddSingle/After',
        );

        // The source table gets the join column; the *_version table gets the mirrored column.
        self::assertSqlContains($sql, ['evolution_article_version', 'category_id']);
    }

    public function testAddingVersionedCollectionProducesNewJoinTable(): void
    {
        $sql = $this->emitUpgradeSql(
            beforePath: __DIR__ . '/../Fixtures/Evolution/AddCollection/Before',
            afterPath: __DIR__ . '/../Fixtures/Evolution/AddCollection/After',
        );

        self::assertSqlContains($sql, ['CREATE TABLE', 'evolution_article_version_tags']);
    }

    /**
     * @return list<string>
     */
    private function emitUpgradeSql(string $beforePath, string $afterPath): array
    {
        $connection = $this->createConnection();

        $beforeEm = $this->bootEntityManager($connection, $beforePath);
        (new SchemaTool($beforeEm))->createSchema($this->allMetadata($beforeEm));

        $afterEm = $this->bootEntityManager($connection, $afterPath);

        return (new SchemaTool($afterEm))->getUpdateSchemaSql($this->allMetadata($afterEm));
    }

    private function createConnection(): Connection
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Fixtures'],
            isDevMode: true,
        );

        return DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );
    }

    private function bootEntityManager(Connection $connection, string $fixturePath): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [$fixturePath],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        $entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener(new VersionableMetadataFactory($entityManager)),
        );

        return $entityManager;
    }

    /**
     * @return list<ClassMetadata>
     */
    private function allMetadata(EntityManagerInterface $entityManager): array
    {
        /** @var list<ClassMetadata> $list */
        $list = $entityManager->getMetadataFactory()->getAllMetadata();

        return $list;
    }

    /**
     * @param list<string> $sql
     * @param list<string> $fragments
     */
    private static function assertSqlContains(array $sql, array $fragments): void
    {
        $joined = implode("\n", $sql);

        foreach ($fragments as $fragment) {
            self::assertStringContainsString(
                $fragment,
                $joined,
                'Expected fragment "' . $fragment . '" in SQL:' . PHP_EOL . $joined,
            );
        }
    }

    /**
     * @param list<string> $sql
     */
    private static function assertSqlDoesNotMention(array $sql, string $needle, string $message, string $scope): void
    {
        // For SQLite drop-column emulation, the original table can still appear in a
        // transient SELECT during the swap. We only assert the FINAL state — every CREATE
        // TABLE / ALTER TABLE statement must not list the removed column as a destination.
        foreach ($sql as $statement) {
            $upper = strtoupper($statement);

            if (str_contains($upper, 'CREATE TABLE') || str_contains($upper, 'ALTER TABLE')) {
                if (str_contains($statement, $needle)) {
                    self::fail($message . ' — statement still references "' . $needle . '": ' . $statement);
                }
            }
        }

        self::assertTrue(true, $scope);
    }
}
