<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Fixtures\Account;
use SoureCode\Component\Versionable\Tests\Fixtures\AccountSettings;
use Symfony\Component\Clock\MockClock;

final class InverseOneToOneIntegrationTest extends TestCase
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
        $clock = new MockClock('2026-05-17T10:00:00+00:00');

        $metadataFactory = new VersionableMetadataFactory();
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            new VersionableListener($metadataFactory, $clock),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($metadataFactory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Account::class),
            $this->entityManager->getClassMetadata(AccountSettings::class),
        ]);
    }

    public function testInverseSideOneToOneIsCapturedInSnapshot(): void
    {
        $account = new Account('alice');
        $settings = new AccountSettings($account);

        $this->entityManager->persist($account);
        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        $account->setName('alice-renamed');
        $this->entityManager->flush();

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_account_version WHERE entity_id = ? ORDER BY version ASC',
            [$account->getId()],
        );

        self::assertCount(1, $rows);
        self::assertSame('alice-renamed', $rows[0]['name']);
        self::assertSame($settings->getId(), (int) $rows[0]['settings_id']);
    }
}
