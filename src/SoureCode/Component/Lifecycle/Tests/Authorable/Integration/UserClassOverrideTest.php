<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Authorable\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Lifecycle\EventListener\AuthorableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Lifecycle\Tests\Authorable\Fixtures\ArticleWithInterfaceTypedAuthor;
use SoureCode\Component\Lifecycle\Tests\Authorable\Fixtures\User;

final class UserClassOverrideTest extends TestCase
{
    public function testUserClassOverrideIsUsedAsTargetEntity(): void
    {
        $entityManager = $this->bootEntityManager(userClass: User::class);

        $classMetadata = $entityManager->getClassMetadata(ArticleWithInterfaceTypedAuthor::class);

        self::assertTrue($classMetadata->hasAssociation('createdBy'));
        self::assertSame(User::class, $classMetadata->getAssociationMapping('createdBy')->targetEntity);
    }

    public function testWithoutOverrideInterfaceTypedPropertyThrows(): void
    {
        $entityManager = $this->bootEntityManager(userClass: null);

        $this->expectException(\LogicException::class);

        $entityManager->getClassMetadata(ArticleWithInterfaceTypedAuthor::class);
    }

    /**
     * @param class-string|null $userClass
     */
    private function bootEntityManager(?string $userClass): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
        $connection = DriverManager::getConnection(
            $dsnParser->parse('sqlite:///:memory:'),
            $config,
        );

        $entityManager = new EntityManager($connection, $config);
        $entityManager->getEventManager()->addEventListener(
            [Events::loadClassMetadata],
            new AuthorableMappingListener(new AuthorableMetadataFactory(), $userClass),
        );

        return $entityManager;
    }
}
