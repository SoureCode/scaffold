# sourecode/versionable

Snapshot-history for Doctrine entities. Mark properties with `#[Versioned]`; whenever any of them changes, a row is written to a parallel `<entity>_version` table.

Built on top of [`sourecode/doctrine-extensions`](../DoctrineExtensions/README.md).

## Install

Part of the `scaffold` monorepo — always installed with the rest.

## Quick start

```php
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
class Article
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    private int $id;

    #[Versioned]
    #[ORM\Column]
    private string $title;

    #[Versioned]
    #[ORM\Column(nullable: true)]
    private ?string $body = null;
}
```

Wire the listeners against your `EntityManager`:

```php
$metadataFactory = new VersionableMetadataFactory();

$em->getEventManager()->addEventListener(
    [Events::onFlush, Events::postFlush],
    new VersionableListener($metadataFactory, $clock),
);
$em->getEventManager()->addEventListener(
    [ToolEvents::postGenerateSchema],
    new VersionableSchemaListener($metadataFactory),
);
```

Now every time you change `title` or `body` and flush, a row is appended to `article_version`.

## Behavior

- **Insert:** no snapshot is written.
- **Update with at least one `#[Versioned]` field changed:** one snapshot row.
- **Update touching only non-versioned fields:** no snapshot.
- **Version counter** increments per entity (`1`, `2`, `3`, …).
- **Source entity deletion:** snapshots cascade-delete via FK.
- **Snapshot timing:** writes happen in `postFlush` (after Doctrine assigns ids to newly-inserted collection elements).

## Supported property kinds

| Kind | Schema | Snapshot |
|------|--------|----------|
| Scalar / enum field | mirrored column on the version row | column value |
| `ManyToOne` (owning) | `<field>_id` column | foreign-key value |
| `OneToOne` (either side) | `<field>_id` column | foreign-key value |
| `OneToMany` (inverse collection) | separate `<source>_version_<field>` join table | one row per current element |
| `ManyToMany` | separate `<source>_version_<field>` join table | one row per current element |

### Tracking the target's version

If the target of an association is **also `Versionable`**, the snapshot row stores its current version number:

- Single-cardinality association → extra `<field>_version` column on the version row.
- Collection association → extra `target_version` column on the join table row.

"Current version" means the highest `version` ever written for that target (or `null` if the target has never been versioned yet).

## `<entity>_version` table layout

| column | type | notes |
|--------|------|-------|
| `id` | integer | PK, autoincrement |
| `entity_id` | mirrors source id type | FK to source.id, ON DELETE CASCADE |
| `version` | integer | per-entity counter |
| `created_at` | datetimetz_immutable | snapshot timestamp |
| ... `#[Versioned]` scalar columns ... | mirror source column types | one per versioned field |
| ... `<field>_id` columns ... | matches related entity id type | one per single-card relation |
| ... `<field>_version` columns ... | integer, nullable | one per single-card relation pointing at a `Versionable` target |

`(entity_id, version)` is uniquely indexed.

## Collection join tables (`<entity>_version_<field>`)

| column | type | notes |
|--------|------|-------|
| `version_id` | integer | FK to `<entity>_version.id`, ON DELETE CASCADE |
| `target_id` | matches related entity id type | id of the related element |
| `target_version` | integer, nullable | only when the related class is itself `Versionable` |

PK: `(version_id, target_id)`.

## What triggers a snapshot

- A scalar `#[Versioned]` field changed on `update`.
- A single-card association on a `#[Versioned]` property changed (FK swap).
- A `ManyToMany`-typed `#[Versioned]` collection was modified (Doctrine reports it in scheduled collection updates/deletions).
- A `OneToMany`-typed `#[Versioned]` collection was modified (detected by walking scheduled insertions/deletions of the "many" side and tracing back via `mappedBy`).

## Reading history

The `Versioner` service exposes the snapshot rows:

```php
$versioner->findHistory($entity);
$versioner->findByVersion($entity, 2);
$versioner->findLatestVersion($entity);
```

Each method returns associative arrays (one row each), not entity objects — the version table is a flat snapshot, not a Doctrine entity.

### Reverting an entity

```php
$versioner->applyVersion($entity, 2);
$em->flush(); // writes a new version row capturing the revert
```

Mutates the live entity in place with values from the requested version. Scalar fields are restored via the matching Doctrine type. Single-card associations are re-attached at their *current* state by looking up the stored FK (`$em->find(...)`). Collection associations are cleared and refilled from the snapshot's join rows. Historical state of related entities is **not** restored — if the related target carried a `target_version` at snapshot time, that's only informational; the live current target is what gets re-attached.

Throws `RuntimeException` when the version row does not exist.

## `target_version` contract

When a related target is itself `Versionable`, the snapshot row records its highest-known version number at snapshot time. The value is `null` when the target has never produced a version row yet (it was inserted but never updated). Version numbers start at `1`; `null` is the canonical "no version yet" marker — no `0` sentinel is used.

## Concurrency

Version numbering uses `SELECT MAX(version) + 1` per entity. The unique index on `(entity_id, version)` will reject conflicting concurrent writes; no automatic retry is built in. For high-contention writes, wrap the flush in your own retry loop.
