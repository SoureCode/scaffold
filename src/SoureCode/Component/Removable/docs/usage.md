# Usage patterns

## Entity

Declare the two markers on the entity:

```php
use SoureCode\Component\Authorable\Attribute\DeletedBy;
use SoureCode\Component\Timestampable\Attribute\DeletedAt;

#[ORM\Entity]
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

The bundle ships convenience traits (`DeletedAtTrait`, `DeletedByTrait`) — see [`TimestampableBundle`](../../../Bundle/TimestampableBundle/README.md) and [`AuthorableBundle`](../../../Bundle/AuthorableBundle/README.md).

## Service

Inject the `RemoverInterface` (alias of `Remover`):

```php
use SoureCode\Component\Removable\RemoverInterface;

final class ArticleController
{
    public function __construct(
        private readonly RemoverInterface $remover,
    ) {}

    public function delete(Article $article): Response
    {
        $this->remover->remove($article);
        // …
    }
}
```

The bundle wires the service from `EntityManagerInterface`, `ClockInterface`, `TimestampableMetadataFactory`, `AuthorableMetadataFactory`, and (optionally) `AuthorProviderInterface`.

## Soft remove

```php
$remover->remove($article);                  // fills deletedAt + deletedBy, flushes
$remover->remove($article, flush: false);    // mutates, no flush
```

When `#[DeletedBy]` is present and the author provider returns a non-null actor, that actor is stored on the entity. If the provider returns `null` (or no provider is wired), `deletedBy` stays untouched.

## Hard remove

```php
$remover->remove($article, soft: false);     // delegates to EntityManager::remove, flushes
```

## Restore

```php
$remover->restore($article);                 // clears deletedAt + deletedBy, flushes
$remover->restore($article, flush: false);
```

## Errors

`LogicException` is thrown when the entity has no `#[DeletedAt]` marker — calling `remove()` or `restore()` on an entity without the marker is a wiring bug.
