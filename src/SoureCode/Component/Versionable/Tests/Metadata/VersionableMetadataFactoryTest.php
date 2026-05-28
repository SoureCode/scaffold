<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Metadata;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

final class VersionableMetadataFactoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private VersionableMetadataFactory $factory;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);
        $this->factory = new VersionableMetadataFactory($this->entityManager);
    }

    public function testReturnsEmptyMetadataForUnannotatedClass(): void
    {
        self::assertTrue($this->factory->getMetadataFor(VersionableMetadataFactoryTestPlain::class)->isEmpty());
        self::assertFalse($this->factory->isVersionable(VersionableMetadataFactoryTestPlain::class));
    }

    public function testCollectsBindingsForVersionedEntity(): void
    {
        $metadata = $this->factory->getMetadataFor(VersionableMetadataFactoryTestEntity::class);

        self::assertFalse($metadata->isEmpty());
        self::assertTrue($this->factory->isVersionable(VersionableMetadataFactoryTestEntity::class));
        self::assertSame('version', $metadata->versionField);

        $names = array_map(
            static fn ($binding): string => $binding->property->getName(),
            $metadata->bindings,
        );

        self::assertContains('title', $names);
        self::assertNotContains('id', $names, 'identifier is excluded');
        self::assertNotContains('version', $names, 'our #[Version] counter is excluded');
    }

    public function testIncludesAssociationRegisteredByLoadClassMetadataListener(): void
    {
        // Simulate AuthorableMappingListener: hook loadClassMetadata, then
        // programmatically call mapManyToOne() to introduce an association
        // without ORM attributes on the property. Then ask the factory for
        // bindings — it must pick up the listener-added association.
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $em = new EntityManager($connection, $config);

        $em->getEventManager()->addEventListener(
            [\Doctrine\ORM\Events::loadClassMetadata],
            new class {
                public function loadClassMetadata(\Doctrine\ORM\Event\LoadClassMetadataEventArgs $args): void
                {
                    $classMetadata = $args->getClassMetadata();

                    if ($classMetadata->getName() !== VersionableMetadataFactoryTestEntity::class) {
                        return;
                    }

                    $classMetadata->mapManyToOne([
                        'fieldName' => 'partner',
                        'targetEntity' => VersionableMetadataFactoryTestPartner::class,
                        'joinColumns' => [['name' => 'partner_id', 'referencedColumnName' => 'id', 'nullable' => true]],
                    ]);
                }
            },
        );

        $factory = new VersionableMetadataFactory($em);
        $metadata = $factory->getMetadataFor(VersionableMetadataFactoryTestEntity::class);

        $names = array_map(
            static fn ($binding): string => $binding->property->getName(),
            $metadata->bindings,
        );

        self::assertContains('partner', $names, 'association added via mapManyToOne() in a listener is picked up from ClassMetadata');
    }

    public function testCachesMetadataPerClass(): void
    {
        $first = $this->factory->getMetadataFor(VersionableMetadataFactoryTestEntity::class);
        $second = $this->factory->getMetadataFor(VersionableMetadataFactoryTestEntity::class);

        self::assertSame($first, $second);
    }

    public function testVersionTableNameAppendsSuffix(): void
    {
        self::assertSame('article_version', $this->factory->versionTableName('article'));
        self::assertSame('user_profile_version', $this->factory->versionTableName('user_profile'));
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'versionable_metadata_factory_test_plain')]
class VersionableMetadataFactoryTestPlain
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;
}

#[ORM\Entity]
#[ORM\Table(name: 'versionable_metadata_factory_test_partner')]
class VersionableMetadataFactoryTestPartner
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;
}

#[ORM\Entity]
#[ORM\Table(name: 'versionable_metadata_factory_test_entity')]
#[Versioned]
class VersionableMetadataFactoryTestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    public int $version = 0;

    #[ORM\Column(type: Types::STRING)]
    public string $title = '';

    public ?VersionableMetadataFactoryTestPartner $partner = null;
}

