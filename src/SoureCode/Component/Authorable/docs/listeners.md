# Listeners

Two listeners. Both opt-in — wire them yourself (the bundle is not yet provided).

## `AuthorableListener`

Events: `prePersist`, `onFlush`.

```php
$listener = new AuthorableListener(
    authorProvider:   $myAuthorProvider, // AuthorProviderInterface
    metadataFactory:  new AuthorableMetadataFactory(),
    changeSetMatcher: new ChangeSetMatcher(), // from doctrine-extensions
);

$em->getEventManager()->addEventListener([Events::prePersist, Events::onFlush], $listener);
```

Behavior:

- `prePersist` fills `#[CreatedBy]` and non-nullable `#[UpdatedBy]` from `$authorProvider->getCurrentAuthor()`. If the provider returns `null`, the listener does nothing on this entity.
- `onFlush` refreshes `#[UpdatedBy]` on scheduled updates, evaluates `#[ChangedBy]`, propagates to indirect watchers (insertions/updates/deletions/collection changes) via the shared `AbstractFlushListener` orchestration.

## `AuthorableMappingListener`

Event: `loadClassMetadata`.

```php
$mappingListener = new AuthorableMappingListener($metadataFactory);
$em->getEventManager()->addEventListener([Events::loadClassMetadata], $mappingListener);
```

For each property carrying `#[CreatedBy]`, `#[UpdatedBy]`, `#[ChangedBy]`, or `#[DeletedBy]` **without** a `#[ORM\ManyToOne]` mapping, registers an association:

| attribute | join column nullable |
|-----------|---------------------|
| `CreatedBy` | `false` |
| `UpdatedBy` | from `nullable:` argument |
| `ChangedBy` | `true` |
| `DeletedBy` | `true` |

`#[DeletedBy]` is a marker only — the flush listener never fills it. The mapping listener registers the association so the soft-delete helper (e.g. `Removable`) has a target to write to.

The target entity is taken from the property's PHP type (must be a non-builtin object type, otherwise throws). If `#[ORM\ManyToOne]` already exists, the listener leaves it alone.

## `AuthorProviderInterface`

```php
namespace SoureCode\Component\Authorable\Author;

interface AuthorProviderInterface
{
    public function getCurrentAuthor(): ?object;
}
```

You provide the implementation. Typical: wrap Symfony's `Security::getUser()`. Background workers can return a system user object or `null`.
