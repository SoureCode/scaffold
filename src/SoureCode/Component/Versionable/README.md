# sourecode/versionable

Snapshot-history for Doctrine entities. Mark an entity with `#[Versioned]`; every persist or change to a mapped field appends a row to a parallel `<entity>_version` table, and loaded entities expose `get<Field>History()` to walk into historical neighbours.

## When to use

You need a per-row audit log of an entity's fields plus pinned relations: "what did this entity look like at version N?" / "what was the author of this post when it was last written?" / "revert this article to last week's title."

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

A `#[Versioned]` entity **must** declare one integer `#[Version]` field — the per-entity snapshot counter. Persist seeds version `1` with a snapshot; every subsequent change to a mapped field (`title`, `body`, …) bumps and appends another row to `article_version`. The identifier and the version field are excluded from the snapshot's data.

## Reference

- [Attributes](docs/attributes.md) — `#[Versioned]`, `#[Version]`.
- [Listeners](docs/listeners.md) — manual wiring (skip if using the bundle).
- [Usage patterns](docs/usage.md) — reading history, reverting, transitive history walks.

## Behavior

| Operation | Snapshot |
|-----------|----------|
| Insert | Yes — seeds version `1` with one snapshot row. |
| Update touching any mapped field | Yes — one row. |
| Source entity deleted | All snapshots cascade-delete (`ON DELETE CASCADE`). |
| Collection mutated (add / remove / clear) | Yes. |

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
| `<field>_version` | only when the target is itself `Versionable` — the version captured at write time |

Collection fields live in a `<entity>_version_<field>` join table; each join row carries `target_id` and (for versioned targets) `target_version`.

## Live-side pin

For every owning single-valued relation whose target is itself `#[Versioned]`, Versionable adds a sibling `<field>_version` column to the **live** owning table next to its `<field>_id`. The pin is written through DBAL on every flush of the owner — capturing the related entity's current version at that moment — and stays frozen until the owner is next flushed.

```text
post.author_id      = 42
post.author_version = 3        ← pin: the author was at v=3 when post was last written
```

## Reading history

```php
$versioner->findHistory(Article::class, $id);          // list of ArticleHistory, oldest → newest
$versioner->findByVersion(Article::class, $id, 2);     // one ArticleHistory or null
$versioner->findLatestVersion(Article::class, $id);    // one ArticleHistory or null
$versioner->applyVersion($article, 2);                  // mutate the live entity in place
```

The first three return a generated `*History` class (a read-only DTO under `SoureCode\Versionable\Generated\…`) — never the live entity class. `applyVersion()` is the only write-side method; it overwrites the entity's mapped fields in place, and the next flush captures the revert as a new snapshot.

## Walking history from the live entity

A loaded entity is a runtime-generated proxy subclass with one `get<Field>History()` method per owning versioned relation. The call reads the live row's pin and returns the partner `*History` at that version:

```php
$post = $em->find(Post::class, $id);

$post->getAuthor();             // live, current Author (normal Doctrine)
$post->getAuthorHistory();      // AuthorHistory at the post's pinned author_version
$post->getAuthorHistory()
     ->getCompany();            // CompanyHistory at the author's recorded company_version
                                 // (transitive: every *History exposes its own relation getters)
```

Promotion to the live entity is the standard Doctrine call:

```php
$em->find(Author::class, $post->getAuthorHistory()->getId());
```

## Composition

The whole entity is versioned, so fields managed by other behaviors are captured automatically — no extra marking. When the entity also uses [`Lifecycle`](../Lifecycle), its `updatedBy` / `deletedAt` / `deletedBy` fields appear in every snapshot, and the soft-delete transition (`null → timestamp`) and `restore()` each produce one.

## Limits

- The `#[Version]` counter is bumped through Doctrine's persister in the same flush. Concurrent writers can compute the same next version; the `(entity_id, version)` unique index rejects the loser. There is no automatic retry — wrap the flush in a retry loop, or add Doctrine optimistic locking (`#[ORM\Version]`) on a *separate* field if the workload needs it.
- Pinning to version `0` is forbidden by design. Since persist seeds version `1`, the case only arises when assigning an unpersisted relation target.
- `applyVersion()` requires the version to exist; otherwise `RuntimeException`.
- `*History` and entity proxy classes are generated at runtime into the cache dir. They are not visible to IDE/static analysis without a stub generator.

## Stability

`#[Versioned]`, `#[Version]`, the `VersionerInterface` shape, the runtime-generated `*History` class names, and the snapshot table layout are stable. Internal classes (`VersionableListener`, `SnapshotTargetResolver`, `VersionIncrementer`, `SnapshotWriter`, `PinMaintainer`, `HistoryClassFactory`, `EntityProxyFactory`, `VersionableSchemaListener`, `VersionableClassMetadataListener`) are wired through the bundle — depend on the interface, not the listeners.
