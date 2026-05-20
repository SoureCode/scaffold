<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Doctrine\SettingMappingDriver;
use SoureCode\Component\Settings\Factory\SettingFactory;
use SoureCode\Component\Settings\Manager\DoctrineSettingsManager;
use SoureCode\Component\Settings\Model\Setting;

final class DoctrineSettingsManagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineSettingsManager $manager;

    protected function setUp(): void
    {
        $config = ORMSetup::createConfiguration(isDevMode: true);
        $config->setMetadataDriverImpl(new SettingMappingDriver());
        $config->enableNativeLazyObjects(true);

        $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
        $connection = DriverManager::getConnection(
            $dsnParser->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(Setting::class),
        ]);

        $this->manager = new DoctrineSettingsManager(
            entityManager: $this->entityManager,
            settingEntityClassName: Setting::class,
            settingFactory: new SettingFactory(),
        );
    }

    public function testGetReturnsDefaultWhenKeyIsMissing(): void
    {
        self::assertSame('fallback', $this->manager->get('missing', 'fallback'));
        self::assertNull($this->manager->get('missing'));
    }

    public function testHasReturnsFalseWhenKeyIsMissing(): void
    {
        self::assertFalse($this->manager->has('missing'));
    }

    public function testSetAndGetRoundTripScalar(): void
    {
        $this->manager->set('site.title', 'Hello World');

        self::assertSame('Hello World', $this->manager->get('site.title'));
        self::assertTrue($this->manager->has('site.title'));
    }

    public function testSetAndGetRoundTripArray(): void
    {
        $value = ['nested' => ['list' => [1, 2, 3]], 'flag' => true];
        $this->manager->set('complex', $value);

        self::assertSame($value, $this->manager->get('complex'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->manager->set('feature.x', false);
        $this->manager->set('feature.x', true);

        self::assertSame(true, $this->manager->get('feature.x'));
    }

    public function testRemoveDeletesTheSetting(): void
    {
        $this->manager->set('temp', 'value');
        $this->manager->remove('temp');

        self::assertFalse($this->manager->has('temp'));
        self::assertNull($this->manager->get('temp'));
    }

    public function testRemoveOnMissingKeyIsNoop(): void
    {
        $this->manager->remove('does-not-exist');
        $this->expectNotToPerformAssertions();
    }

    public function testAllReturnsEverySettingByKey(): void
    {
        $this->manager->set('a', 1);
        $this->manager->set('b', 'two');
        $this->manager->set('c', null);

        $collection = $this->manager->all();

        self::assertCount(3, $collection);
        self::assertSame(1, $collection->get('a')->getValue());
        self::assertSame('two', $collection->get('b')->getValue());
        self::assertNull($collection->get('c')->getValue());
    }

    public function testInvalidKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->get('Invalid Key');
    }

    public function testNullStoredValueIsReturnedAsNullEvenWhenDefaultGiven(): void
    {
        $this->manager->set('nullable', null);

        self::assertNull($this->manager->get('nullable', 'fallback'));
        self::assertTrue($this->manager->has('nullable'));
    }
}
