# Removable

Soft-remove orchestration for Doctrine entities. Composes [Timestampable](../../Timestampable/docs/index.md)'s `#[DeletedAt]` marker and [Authorable](../../Authorable/docs/index.md)'s `#[DeletedBy]` marker into one repository operation.

## Contents

- [Usage](usage.md) — repository trait, `remove`, `restore`

## Concepts

| Concept | Purpose |
|---------|---------|
| `RemovableRepositoryTrait` | Mixed into a Doctrine `EntityRepository` to expose `remove()` and `restore()` |
| `remove($entity, $soft = true, $flush = true)` | Soft: fill `#[DeletedAt]` and `#[DeletedBy]`; hard: delegate to `EntityManager::remove` |
| `restore($entity, $flush = true)` | Clear both markers |
| `#[DeletedAt]` | Owned by Timestampable — the field the trait fills with the current clock value |
| `#[DeletedBy]` | Owned by Authorable — the field the trait fills with the current author (when an `AuthorProviderInterface` is configured) |

## Composition

Removable does not invent any attribute of its own. It reads the markers shipped by Timestampable and Authorable. This keeps each layer responsible for one thing:

- **Timestampable** — when was something deleted? (`#[DeletedAt]`)
- **Authorable** — who deleted it? (`#[DeletedBy]`)
- **Removable** — orchestrate the soft-remove / restore operation.

Versionable, layered on top, snapshots the marker fields like any other tracked column — see [Versionable's usage notes](../../Versionable/docs/usage.md).
