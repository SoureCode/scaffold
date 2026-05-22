# Listeners (manual wiring)

Use the [`AuthorableBundle`](../../../Bundle/AuthorableBundle/README.md) when running Symfony. This page is for plain Doctrine setups.

## `AuthorableListener`

```php
use Doctrine\ORM\Events;
use SoureCode\Component\Lifecycle\EventListener\AuthorableListener;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;

$listener = new AuthorableListener(
    authorProvider:   $authorProvider, // AuthorProviderInterface
    metadataFactory:  new AuthorableMetadataFactory(),
    changeSetMatcher: new ChangeSetMatcher(),
);

$em->getEventManager()->addEventListener([Events::prePersist, Events::onFlush], $listener);
```

Events: `prePersist`, `onFlush`.

## `AuthorableMappingListener`

```php
use Doctrine\ORM\Events;
use SoureCode\Component\Lifecycle\EventListener\AuthorableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;

$mappingListener = new AuthorableMappingListener(new AuthorableMetadataFactory());

$em->getEventManager()->addEventListener([Events::loadClassMetadata], $mappingListener);
```

Event: `loadClassMetadata`.

Registers `#[ORM\ManyToOne]` for any property carrying an Authorable attribute that does not already declare one. Target entity is read from the property's PHP type (must be a non-builtin object). Existing `#[ORM\ManyToOne]` is left alone.

| attribute | join column nullable |
|-----------|----------------------|
| `CreatedBy` | `false` |
| `UpdatedBy` | from `nullable:` argument |
| `ChangedBy` | `true` |
| `DeletedBy` | `true` |

## `AuthorProviderInterface`

```php
namespace SoureCode\Component\Lifecycle\Author;

interface AuthorProviderInterface
{
    public function getCurrentAuthor(): ?object;
}
```

Implement once at the integration layer. Return either the current user entity or `null`.
