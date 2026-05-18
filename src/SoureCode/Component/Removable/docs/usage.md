# Usage patterns

## Entity

Declare the two markers on the entity:

```php
use SoureCode\Component\Authorable\Attribute\DeletedBy;
use SoureCode\Component\Timestampable\Attribute\DeletedAt;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    private int $id;

    #[ORM\Column]
    private string $title;

    #[DeletedAt]
    private ?\DateTimeImmutable $deletedAt = null;

    #[DeletedBy]
    private ?User $deletedBy = null;
}
```

`#[DeletedBy]` is optional. If absent, only `#[DeletedAt]` is filled on `remove()`.

## Repository

Mix `RemovableRepositoryTrait` into a Doctrine repository. The trait declares three abstract hooks the repository must satisfy:

```php
final class ArticleRepository extends EntityRepository
{
    use RemovableRepositoryTrait;

    public function __construct(
        EntityManagerInterface $entityManager,
        ClassMetadata $classMetadata,
        private readonly ClockInterface $clock,
        private readonly ?AuthorProviderInterface $authorProvider = null,
    ) {
        parent::__construct($entityManager, $classMetadata);
    }

    protected function getClock(): ClockInterface
    {
        return $this->clock;
    }

    protected function getAuthorProvider(): ?AuthorProviderInterface
    {
        return $this->authorProvider;
    }
}
```

The bundle ships an `AbstractRemovableRepository` that does this wiring through DI.

## Soft remove

```php
$repository->remove($article);              // fills deletedAt + deletedBy, flushes
$repository->remove($article, flush: false); // mutates, no flush
```

When `#[DeletedBy]` is present and the author provider returns a non-null actor, that actor is stored on the entity. If the provider returns `null` (or no provider is wired), `deletedBy` stays untouched.

## Hard remove

```php
$repository->remove($article, soft: false); // delegates to EntityManager::remove, flushes
```

## Restore

```php
$repository->restore($article);             // clears deletedAt + deletedBy, flushes
$repository->restore($article, flush: false);
```

## Errors

The trait throws `LogicException` when the entity has no `#[DeletedAt]` marker — mixing the trait into a repository for an entity without the marker is a wiring bug, not a runtime condition.
