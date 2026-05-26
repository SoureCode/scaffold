# sourecode/versionable

Snapshot-history for Doctrine entities. Mark an entity with `#[Versioned]`; every change to any of its mapped fields appends a row to a parallel `<entity>_version` table.

## When to use

You need a per-row audit log of an entity's fields: "what did this entity look like at version N?" / "revert this article to last week's title."

## When not to use

You need a full transactional audit log of every actor on every field, event-sourcing semantics, or a fully searchable history store. Versionable stores flat snapshots, not events. Look at a dedicated audit / event-sourcing library for those needs.

## Install

Part of the `scaffold` monorepo. The [`versionable-bundle`](../../Bundle/VersionableBundle/README.md) wires everything; without it, see [`docs/listeners.md`](docs/listeners.md).

## Minimal example

```php
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[Versioned]
class Article
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    private int $id;

    #[Version]
    #[ORM\Column]
    private int $version = 0;

    #[ORM\Column]
    private string $title;

    #[ORM\Column(nullable: true)]
    private ?string $body = null;

    public function getVersion(): int
    {
        return $this->version;
    }
}
```

A `#[Versioned]` entity **must** declare one integer `#[Version]` field — the per-entity counter the framework maintains. Every change to a mapped field (`title`, `body`, …) bumps it and appends a row to `article_version`. The identifier and the version field are excluded from the snapshot's data. Insert is not a snapshot — a fresh entity is version `0`, its first snapshot is `1`.

## Reference

- [Attributes](docs/attributes.md) — `#[Versioned]`, `#[Version]`.
- [Listeners](docs/listeners.md) — manual wiring (skip if using the bundle).
- [Usage patterns](docs/usage.md) — reading history, reverting, composition.

## Behavior

| Operation | Snapshot |
|-----------|----------|
| Insert | No. |
| Update touching any mapped field | Yes — one row. |
| Source entity deleted | All snapshots cascade-delete. |
| Collection mutated (add/remove) | Yes. |

Version numbers start at `1` and increment per entity. `(entity_id, version)` is uniquely indexed.

## Snapshot row

| column | role |
|--------|------|
| `id` | autoincrement PK |
| `entity_id` | FK to source row, `ON DELETE CASCADE` |
| `version` | per-entity counter |
| `created_at` | snapshot timestamp |
| `<scalar>` | one column per mapped scalar / enum field |
| `<field>_id` | one column per single-card relation |
| `<field>_version` | only when the target is itself `Versionable` — its current version |

Collection fields live in a `<entity>_version_<field>` join table.

## Reading history

```php
$versioner->findHistory(Article::class, $id);          // ordered list of rows
$versioner->findByVersion(Article::class, $id, 2);     // one row or null
$versioner->findLatestVersion(Article::class, $id);    // one row or null
$versioner->applyVersion($article, 2);                  // mutate in place
```

`applyVersion()` overwrites the entity's mapped fields in place. Related entities are re-attached at their *current* state — historical state of the targets is not restored. Persisting the entity afterwards produces a new snapshot capturing the revert.

## Composition

The whole entity is versioned, so fields managed by other behaviors are captured automatically — no extra marking. When the entity also uses [`Lifecycle`](../Lifecycle), its `updatedBy` / `deletedAt` / `deletedBy` fields appear in every snapshot, and the soft-delete transition (`null → timestamp`) and `restore()` each produce one.

## Limits

- The `#[Version]` counter is bumped through Doctrine's persister in the same flush. Concurrent writers can compute the same next version; the `(entity_id, version)` unique index rejects the loser. There is no automatic retry — wrap the flush in a retry loop, or add Doctrine optimistic locking (`#[ORM\Version]`) on a *separate* field if the workload needs it.
- `target_version` is informational. Reverting does not restore historical state of related entities.
- `applyVersion()` requires the version to exist; otherwise `RuntimeException`.

## Stability

`#[Versioned]`, `#[Version]`, the `VersionerInterface` shape, and the snapshot table layout are stable. Internal classes (`VersionableListener`, `SnapshotTargetResolver`, `VersionIncrementer`, `SnapshotWriter`, `VersionableSchemaListener`) are wired through the bundle — depend on the interface, not the listeners.
