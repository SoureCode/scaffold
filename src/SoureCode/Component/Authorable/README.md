# sourecode/authorable

Automatic `createdBy` / `updatedBy` / `changedBy` tracking for Doctrine entities. Stores a reference to the author entity (`ManyToOne`).

Built on top of [`sourecode/doctrine-extensions`](../DoctrineExtensions/README.md).

## Install

Part of the `scaffold` monorepo — always installed with the rest. See the root [README](../../../../README.md).

## Quick start

```php
use SoureCode\Component\Authorable\Attribute\CreatedBy;
use SoureCode\Component\Authorable\Attribute\UpdatedBy;

#[ORM\Entity]
class Article
{
    #[CreatedBy]
    private ?User $createdBy = null;

    #[UpdatedBy]
    private ?User $updatedBy = null;
}
```

Provide an author source:

```php
final class SymfonySecurityAuthorProvider implements AuthorProviderInterface
{
    public function __construct(private readonly Security $security) {}

    public function getCurrentAuthor(): ?object
    {
        return $this->security->getUser();
    }
}
```

Wire the listeners:

```php
$metadataFactory = new AuthorableMetadataFactory();

$listener = new AuthorableListener($authorProvider, $metadataFactory, new ChangeSetMatcher());
$mappingListener = new AuthorableMappingListener($metadataFactory);

$em->getEventManager()->addEventListener([Events::prePersist, Events::onFlush], $listener);
$em->getEventManager()->addEventListener([Events::loadClassMetadata], $mappingListener);
```

## Docs

- [Attributes](docs/attributes.md)
- [Listeners and wiring](docs/listeners.md)
- [Usage patterns](docs/usage.md)
