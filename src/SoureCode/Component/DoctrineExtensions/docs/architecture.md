# Architecture

## Layers

```
+-------------------------------------------+
|  Consumer package (Timestampable, ...)    |
|  - Attributes                             |
|  - Concrete Bindings (implement contracts)|
|  - Concrete Metadata + Factory            |
|  - Listener (extends AbstractFlushListener)
+-------------------------------------------+
                    |
                    v
+-------------------------------------------+
|  doctrine-extensions                      |
|  - Binding contracts                      |
|  - Metadata contracts                     |
|  - ChangeSetMatcher                       |
|  - AbstractFlushListener                  |
+-------------------------------------------+
                    |
                    v
+-------------------------------------------+
|  doctrine/orm                             |
+-------------------------------------------+
```

## Binding kinds

| Interface | Lifecycle | Extra data |
|-----------|-----------|------------|
| `PersistBindingInterface` | `prePersist` only, when property is null | property |
| `UpdateBindingInterface` | `prePersist` (unless `isNullable()`) + every `onFlush` | property + nullable flag |
| `ChangeBindingInterface` | `onFlush` when a watched field is in the changeset | property + fields + optional value matcher |

## ChangeSetMatcher

`matches($binding, $entity, $unitOfWork) : bool`

- Iterates `binding.getFields()`
- For each field: walks dotted paths (`a.b.c`)
- Descends into `Collection`s and object refs
- Checks the UoW's changeset for the leaf field
- Cycle-protected via `SplObjectStorage`
- Backed-enum value normalization

## AbstractFlushListener

Template-method base for any "react on flush" listener.

### `prePersist`
1. Skip if `shouldRun() === false`
2. If metadata present: fill `PersistBindings` and non-nullable `UpdateBindings`
3. Else: `handlePersistInterfaceFallback($entity)`

### `onFlush`
1. Skip if `shouldRun() === false`
2. For each `ScheduledEntityUpdate`: refresh `UpdateBindings`, evaluate `ChangeBindings`, recompute changeset
3. For each `ScheduledEntityUpdate|Insertion|Deletion`: scan identity map for owners watching this related entity (deletion ignores value matcher)
4. For each `ScheduledCollectionUpdate|Deletion`: touch owners with a `ChangedAt` binding naming the collection
5. Calls `scheduleExtraUpdate` if needed
6. Always `recomputeSingleEntityChangeSet` after touching

### Subclass hooks

```php
abstract protected function shouldRun(): bool;
abstract protected function resolveValue(\ReflectionProperty $property): mixed;
abstract protected function handlePersistInterfaceFallback(object $entity): void;
abstract protected function handleUpdateInterfaceFallback(object $entity, EntityManagerInterface $em, UnitOfWork $uow): void;
```
