# Versionable

Snapshot-history for Doctrine entities driven by a PHP attribute. Whenever any `#[Versioned]` property changes, a row is appended to a parallel `<entity>_version` table.

Built on top of [`sourecode/doctrine-extensions`](../../DoctrineExtensions/docs/index.md).

## Contents

- [Attributes](attributes.md) — `#[Versioned]`
- [Listeners](listeners.md) — `VersionableListener`, `VersionableSchemaListener`
- [Usage](usage.md) — wiring, repository, revert, composition with other behaviors

## Concepts

| Concept | Purpose |
|---------|---------|
| `#[Versioned]` | Mark a property as tracked; its value participates in snapshot rows |
| `VersionableMetadataFactory` | Reflects classes and caches their `#[Versioned]` bindings |
| `VersionableListener` | Detects changes on `onFlush` and writes snapshot rows on `postFlush` |
| `VersionableSchemaListener` | Generates the `<entity>_version` table (and join tables) during schema build |
| `VersionableRepositoryTrait` | Read history, fetch a specific version, revert an entity to a past version |
| `target_version` | When a snapshotted relation points at a `Versionable` target, the target's current version is recorded too |

## Snapshot triggers

- Update touching at least one `#[Versioned]` scalar/relation field.
- `ManyToMany` or `OneToMany` `#[Versioned]` collection mutated (add/remove).
- Inverse-side child inserted/deleted that reaches a `Versionable` owner via `mappedBy`.

Inserts do not produce a snapshot. The initial state is the entity row itself; version `1` is written the first time it changes.
