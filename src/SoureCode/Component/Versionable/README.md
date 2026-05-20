# sourecode/versionable

Snapshot-history for Doctrine entities. Mark properties with `#[Versioned]`; every change to one of them appends a row to a parallel `<entity>_version` table.

## When to use

You need a per-row audit log of selected fields: "what did this entity look like at version N?" / "revert this article to last week's title."

## When not to use

You need a full transactional audit log of every actor on every field, event-sourcing semantics, or a fully searchable history store. Versionable stores flat snapshots, not events. Look at a dedicated audit / event-sourcing library for those needs.

## Install

Part of the `scaffold` monorepo. The [`versionable-bundle`](../../Bundle/VersionableBundle/README.md) wires everything; without it, see [`docs/listeners.md`](docs/listeners.md).

## Minimal example

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

Every change to `title` or `body` appends a row to `article_version`. Insert is not a snapshot — the entity row itself is version 0.

## Reference

- [Attributes](docs/attributes.md) — `#[Versioned]`.
- [Listeners](docs/listeners.md) — manual wiring (skip if using the bundle).
- [Usage patterns](docs/usage.md) — reading history, reverting, composition.

## Behavior

| Operation | Snapshot |
|-----------|----------|
| Insert | No. |
| Update touching a `#[Versioned]` field | Yes — one row. |
| Update touching only non-versioned fields | No. |
| Source entity deleted | All snapshots cascade-delete. |
| Versioned collection mutated (add/remove) | Yes. |

Version numbers start at `1` and increment per entity. `(entity_id, version)` is uniquely indexed.

## Snapshot row

| column | role |
|--------|------|
| `id` | autoincrement PK |
| `entity_id` | FK to source row, `ON DELETE CASCADE` |
| `version` | per-entity counter |
| `created_at` | snapshot timestamp |
| `<scalar>` | one column per versioned scalar / enum field |
| `<field>_id` | one column per versioned single-card relation |
| `<field>_version` | only when the target is itself `Versionable` — its current version |

Versioned collections live in a `<entity>_version_<field>` join table.

## Reading history

```php
$versioner->findHistory(Article::class, $id);          // ordered list of rows
$versioner->findByVersion(Article::class, $id, 2);     // one row or null
$versioner->findLatestVersion(Article::class, $id);    // one row or null
$versioner->applyVersion($article, 2);                  // mutate in place
```

`applyVersion()` overwrites versioned fields in place. Related entities are re-attached at their *current* state — historical state of the targets is not restored. Persisting the entity afterwards produces a new snapshot capturing the revert.

## Composition

- [`Authorable`](../Authorable/README.md) — mark `#[UpdatedBy]` as `#[Versioned]` to record the author per snapshot.
- [`Timestampable`](../Timestampable/README.md) / [`Removable`](../Removable/README.md) — mark `#[DeletedAt]` `#[Versioned]` to capture the soft-delete transition.

## Limits

- Concurrent writers competing for the same `(entity_id, version)` are rejected by the unique index. No automatic retry — wrap your flush in a retry loop if the workload needs it.
- `target_version` is informational. Reverting does not restore historical state of related entities.
- `applyVersion()` requires the version to exist; otherwise `RuntimeException`.

## Stability

`#[Versioned]`, the `VersionerInterface` shape, and the snapshot table layout are stable. Internal classes (`VersionableListener`, `VersionableSchemaListener`) are wired through the bundle — depend on the interface, not the listeners.
