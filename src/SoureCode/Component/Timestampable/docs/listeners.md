# Listeners

Two listeners. Both opt-in — wire them yourself (the bundle is not yet provided).

## `TimestampableListener`

Events: `prePersist`, `onFlush`.

```php
$listener = new TimestampableListener(
    metadataFactory:  new TimestampableMetadataFactory(),
    timestampFactory: new TimestampFactory($clock), // SoureCode\Component\Timestampable\Clock
    changeSetMatcher: new ChangeSetMatcher(),
);

$em->getEventManager()->addEventListener([Events::prePersist, Events::onFlush], $listener);
```

### `prePersist`

- Fills `CreatedAt` properties (if `null`)
- Fills `UpdatedAt` properties unless `nullable: true`

### `onFlush`

- Iterates `getScheduledEntityUpdates()` → refreshes `UpdatedAt`, evaluates `ChangedAt` against the own changeset
- Iterates `getScheduledEntityInsertions()` → fires watchers via newly-assigned related entity
- Iterates `getScheduledEntityDeletions()` → fires watchers on owners that point at the deleted entity (value matcher ignored)
- Iterates `getScheduledCollectionUpdates()` + `getScheduledCollectionDeletions()` → fires watchers that name the collection
- Calls `recomputeSingleEntityChangeSet` for every touched entity
- Schedules extra updates for owners that weren't already dirty (collection case)

## `TimestampableMappingListener`

Event: `loadClassMetadata`.

```php
$mappingListener = new TimestampableMappingListener($metadataFactory);
$em->getEventManager()->addEventListener([Events::loadClassMetadata], $mappingListener);
```

For each property carrying `#[CreatedAt]`, `#[UpdatedAt]`, `#[ChangedAt]`, or `#[DeletedAt]` **without** a `#[ORM\Column]` mapping, registers a column:

| attribute | nullable column |
|-----------|-----------------|
| `CreatedAt` | `false` |
| `UpdatedAt` | from `nullable:` argument |
| `ChangedAt` | `true` |
| `DeletedAt` | `true` |

`#[DeletedAt]` is a marker only — the flush listener never fills it. The mapping listener registers the column so consumers can drop the attribute onto a property and rely on the soft-delete helper (e.g. `Removable`) to set it.

If `#[ORM\Column]` already exists, the listener leaves it alone — you can always override.
