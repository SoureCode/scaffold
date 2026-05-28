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
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\Fixtures\Category;
use SoureCode\Component\Versionable\Tests\Fixtures\Comment;
use SoureCode\Component\Versionable\Tests\Fixtures\Node;
use SoureCode\Component\Versionable\Tests\Fixtures\Profile;
use SoureCode\Component\Versionable\Tests\Fixtures\RichArticle;
use SoureCode\Component\Versionable\Tests\Fixtures\Tag;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

/**
 * Exercises {@see Versioner::applyVersion} with the `cascade: true` flag.
 * The cascade walks each versioned association on the target entity's
 * version row and, for every related entity that is itself versionable,
 * recursively reverts it to the version captured at the parent snapshot.
 */
final class CascadeRestoreIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Versioner $versioner;

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

        $metadataFactory = new VersionableMetadataFactory($this->entityManager);
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($metadataFactory, $clock),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($metadataFactory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Category::class),
            $this->entityManager->getClassMetadata(Profile::class),
            $this->entityManager->getClassMetadata(Tag::class),
            $this->entityManager->getClassMetadata(RichArticle::class),
            $this->entityManager->getClassMetadata(Comment::class),
            $this->entityManager->getClassMetadata(Node::class),
        ]);

        $this->versioner = new Versioner($this->entityManager, $metadataFactory);
    }

    /**
     * Cascade reads the `category_version` captured on the article's v1
     * row and reverts the live Category entity to that version — even
     * when the category has been mutated independently afterwards.
     */
    public function testCascadeRevertsRelatedEntityToTheVersionCapturedOnTheParentRow(): void
    {
        $category = new Category('one');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $category->setName('two');
        $this->entityManager->flush();

        $article = new RichArticle('hello');
        $article->setCategory($category);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('hello-2');
        $this->entityManager->flush();

        $capturedCategoryVersion = $this->fetchCapturedCategoryVersion($article->getId(), articleVersion: 1);
        self::assertNotNull($capturedCategoryVersion, 'precondition: the article version row must reference a captured category version');

        // Read the category's name at that captured version so we know what
        // the cascade is expected to restore the live entity to.
        $expectedCategoryName = $this->fetchCategoryNameAtVersion($category->getId(), $capturedCategoryVersion);
        self::assertNotNull($expectedCategoryName, 'precondition: the referenced category version row must exist');

        // Mutate the category independently so its live state differs.
        $category->setName('three');
        $this->entityManager->flush();

        $nameProperty = new \ReflectionProperty(Category::class, 'name');
        self::assertSame('three', $nameProperty->getValue($category), 'precondition: category is live at the latest state before cascade');

        $this->versioner->applyVersion($article, 1, cascade: true);

        self::assertSame($expectedCategoryName, $nameProperty->getValue($category), 'cascade walked the association and reverted the related entity to the captured version');
    }

    /**
     * `$onlyFields` is the cascade walk boundary. Associations not listed
     * in it are not visited, even if the parent row has a captured
     * target version available.
     */
    public function testCascadeRespectsOnlyFieldsBoundary(): void
    {
        $category = new Category('one');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $category->setName('two');
        $this->entityManager->flush();

        $article = new RichArticle('hello');
        $article->setCategory($category);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setTitle('hello-2');
        $this->entityManager->flush(); // article v1

        $category->setName('three');
        $this->entityManager->flush();

        $this->versioner->applyVersion($article, 1, onlyFields: ['title'], cascade: true);

        $nameProperty = new \ReflectionProperty(Category::class, 'name');
        self::assertSame('three', $nameProperty->getValue($category), 'cascade must skip associations not in onlyFields');
    }

    /**
     * The cascade walk seeds an SplObjectStorage so that re-entering the
     * same root via a different association is a no-op rather than an
     * infinite recursion. Two top-level applyVersion calls on the same
     * entity each return a populated AppliedVersion — proving the guard
     * does not corrupt subsequent calls.
     */
    /**
     * For a many-to-many association the cascade walk loads the join-table
     * rows captured at the parent version and applies the captured target
     * version to each related entity individually.
     */
    public function testCascadeWalksManyToManyCollectionAndRevertsEachElement(): void
    {
        $tagA = new Tag('a-one');
        $tagB = new Tag('b-one');
        $this->entityManager->persist($tagA);
        $this->entityManager->persist($tagB);
        $this->entityManager->flush();

        $tagA->setName('a-two');
        $tagB->setName('b-two');
        $this->entityManager->flush(); // tag v1 for each

        $article = new RichArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->addTag($tagA);
        $article->addTag($tagB);
        $this->entityManager->flush(); // article v1, captures tags @ v1

        $tagA->setName('a-three');
        $tagB->setName('b-three');
        $this->entityManager->flush();

        $nameProperty = new \ReflectionProperty(Tag::class, 'name');
        self::assertSame('a-three', $nameProperty->getValue($tagA), 'precondition: tags are at the latest mutated state before cascade');
        self::assertSame('b-three', $nameProperty->getValue($tagB));

        // article v=2 is the snapshot taken when tags were added; it captures
        // each tag at the version the tag held in memory at that moment.
        $capturedTagAVersion = $this->fetchCapturedTagVersion($article->getId(), articleVersion: 2, tagId: $tagA->getId());
        $capturedTagBVersion = $this->fetchCapturedTagVersion($article->getId(), articleVersion: 2, tagId: $tagB->getId());
        self::assertNotNull($capturedTagAVersion);
        self::assertNotNull($capturedTagBVersion);

        $expectedTagAName = $this->fetchTagNameAtVersion($tagA->getId(), $capturedTagAVersion);
        $expectedTagBName = $this->fetchTagNameAtVersion($tagB->getId(), $capturedTagBVersion);

        $this->versioner->applyVersion($article, 2, cascade: true);

        self::assertSame($expectedTagAName, $nameProperty->getValue($tagA), 'cascade reverts every M2M element to the captured version');
        self::assertSame($expectedTagBName, $nameProperty->getValue($tagB));
    }

    /**
     * Cycle protection: a versioned self-reference must not cause infinite
     * recursion. The cascade pushes the entity into the visited set on
     * entry, so when the same instance is reached again through its own
     * `parent` association the recursive call returns immediately.
     */
    public function testCascadeShortCircuitsOnSelfReferencingCycle(): void
    {
        $node = new Node('label-a');
        $this->entityManager->persist($node);
        $this->entityManager->flush();

        $node->setLabel('label-b');
        $this->entityManager->flush(); // node v1, parent_version = null (no parent set)

        // Now bind parent to self and bump again — this v2 row captures
        // parent_id = self.id AND parent_version = 1 (the prior version).
        $node->setParent($node);
        $node->setLabel('label-c');
        $this->entityManager->flush();

        $node->setLabel('label-d');
        $this->entityManager->flush();

        // Restoring v3 walks the parent association; the parent is the
        // same instance, so the recursion hits the visited guard.
        $applied = $this->versioner->applyVersion($node, 3, cascade: true);

        $labelProperty = new \ReflectionProperty(Node::class, 'label');
        self::assertSame('label-c', $labelProperty->getValue($node), 'cascade revert applied to the self-referencing node');
        self::assertSame(3, $applied->version);
    }

    /**
     * The M2M cascade skips rows whose captured target_version is null —
     * i.e. the element was attached at snapshot time before the target
     * entity itself had any version rows.
     */
    public function testCascadeSkipsManyToManyElementWithoutCapturedTargetVersion(): void
    {
        $tag = new Tag('tag-a');
        $this->entityManager->persist($tag);
        $this->entityManager->flush(); // tag inserted, no version row yet

        $article = new RichArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->addTag($tag);
        $this->entityManager->flush(); // article v1, captures tag with target_version = null

        // Mutate the tag — but no cascade should touch it because the
        // captured snapshot has no target_version recorded.
        $tag->setName('tag-b');
        $this->entityManager->flush();

        $applied = $this->versioner->applyVersion($article, 1, cascade: true);

        $nameProperty = new \ReflectionProperty(Tag::class, 'name');
        self::assertSame('tag-b', $nameProperty->getValue($tag), 'M2M cascade skips elements with null captured target_version');
        self::assertSame(1, $applied->version);
    }

    /**
     * A captured M2M element whose live row was hard-deleted between
     * snapshot and cascade. The cascade walk silently skips the missing
     * target rather than throwing — the snapshot itself remains intact
     * for forensic queries through {@see Versioner::history}.
     */
    public function testCascadeSkipsDeletedManyToManyTargetSilently(): void
    {
        $tagA = new Tag('a-one');
        $this->entityManager->persist($tagA);
        $this->entityManager->flush();

        $tagA->setName('a-two');
        $this->entityManager->flush();

        $article = new RichArticle('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->addTag($tagA);
        $this->entityManager->flush();

        // Hard-delete the tag — the captured target in the snapshot now
        // points at nothing in the live table.
        $article->removeTag($tagA);
        $this->entityManager->remove($tagA);
        $this->entityManager->flush();

        // Cascade must not throw even though the target is gone.
        $applied = $this->versioner->applyVersion($article, 1, cascade: true);

        self::assertSame(1, $applied->version);
    }

    /**
     * Calling applyVersion with an entity that has no identifier yet is a
     * programmer error: the snapshot row keys off the identifier, so there
     * is no version to apply. The applier rejects it explicitly.
     */
    public function testApplyVersionRejectsEntityWithoutIdentifier(): void
    {
        $unmanaged = new Node('drifting');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot apply version to');

        $this->versioner->applyVersion($unmanaged, 1);
    }

    private function fetchCapturedCategoryVersion(int $articleId, int $articleVersion): ?int
    {
        $value = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('category' . VersionTableColumns::SINGLE_ASSOC_VERSION_SUFFIX)
            ->from('versionable_rich_article_version')
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere(VersionTableColumns::VERSION . ' = :version')
            ->setParameter('entity_id', $articleId)
            ->setParameter('version', $articleVersion)
            ->fetchOne();

        if ($value === false || $value === null) {
            return null;
        }

        return (int) $value;
    }

    private function fetchCategoryNameAtVersion(int $categoryId, int $version): ?string
    {
        return $this->fetchNameAtVersion('versionable_category_version', $categoryId, $version);
    }

    private function fetchTagNameAtVersion(int $tagId, int $version): ?string
    {
        return $this->fetchNameAtVersion('versionable_tag_version', $tagId, $version);
    }

    private function fetchCapturedTagVersion(int $articleId, int $articleVersion, int $tagId): ?int
    {
        $value = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('jt.' . VersionTableColumns::JOIN_TARGET_VERSION)
            ->from('versionable_rich_article_version_tags', 'jt')
            ->innerJoin('jt', 'versionable_rich_article_version', 'av', 'av.id = jt.' . VersionTableColumns::JOIN_VERSION_ID)
            ->where('av.' . VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere('av.' . VersionTableColumns::VERSION . ' = :version')
            ->andWhere('jt.' . VersionTableColumns::JOIN_TARGET_ID . ' = :target_id')
            ->setParameter('entity_id', $articleId)
            ->setParameter('version', $articleVersion)
            ->setParameter('target_id', $tagId)
            ->fetchOne();

        if ($value === false || $value === null) {
            return null;
        }

        return (int) $value;
    }

    private function fetchNameAtVersion(string $versionTable, int $entityId, int $version): ?string
    {
        $value = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('name')
            ->from($versionTable)
            ->where(VersionTableColumns::ENTITY_ID . ' = :entity_id')
            ->andWhere(VersionTableColumns::VERSION . ' = :version')
            ->setParameter('entity_id', $entityId)
            ->setParameter('version', $version)
            ->fetchOne();

        if ($value === false || $value === null) {
            return null;
        }

        return (string) $value;
    }
}
