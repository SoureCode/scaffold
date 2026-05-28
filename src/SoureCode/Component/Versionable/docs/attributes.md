# Attributes

## `#[Versioned]`

```php
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[Versioned]
class Article
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    private int $id;

    #[ORM\Column]
    private string $title;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    private ?Author $author = null;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;
}
```

Marks the entity as versioned. Every mapped field and association Doctrine knows about — except the identifier, the `#[Version]` counter, and any Doctrine-`#[ORM\Version]` optimistic-lock field — is tracked; persist seeds version `1` with a snapshot, and any subsequent change to a versioned field appends another snapshot row.

The binding inventory is read from Doctrine's `ClassMetadata` rather than from property-level ORM attributes, so any mapping introduced through a `loadClassMetadata` listener (e.g. lifecycle's `AuthorableMappingListener` that adds the `createdBy` `ManyToOne`) is captured exactly like a directly-attributed field. XML / YAML mappings work the same way.

### Arguments

```php
#[Versioned(bumpRelations: false)]
class AuditEntry { … }
```

| Argument | Default | Meaning |
|----------|---------|---------|
| `bumpRelations` | `null` | Per-class override for "does a relationship change ripple to the other end's snapshot?" — `true` bumps both sides on a relation change, `false` only bumps the side that owns the change. `null` (the default) means "no opinion — use the global default" (configured via `versionable.bump_relations`, which itself defaults to `true`). The runtime one-shot `Versioner::bumpRelations(bool)` still wins over both. |

Target: `\Attribute::TARGET_CLASS`. Mark the root of an inheritance hierarchy once; subclasses are versioned too.

### How each field kind is stored

| Kind | Where it lives |
|------|----------------|
| Scalar / enum field | column on the snapshot row |
| Embeddable | flattened columns on the snapshot row |
| `ManyToOne` (owning) | `<field>_id` column on the snapshot row, plus a sibling `<field>_version` on the **live** owning table when the target is versioned (the live-side pin) |
| `OneToOne` (either side) | `<field>_id` column on the snapshot row, plus the live-side pin when versioned |
| `OneToMany` (inverse collection) | one row per element in `<entity>_version_<field>` |
| `ManyToMany` | one row per element in `<entity>_version_<field>` |

### `<field>_version` on the live table (pin)

For every owning single-valued versioned relation where the target is itself versioned, Versionable adds an unmapped `<field>_version` column to the LIVE table next to the FK. On every flush of the owner, `PinMaintainer` writes the related entity's current version into that column. The pin then stays frozen until the owner is flushed again — so the entity's view of its partner does not change underneath it when the partner bumps independently.

### `target_version`

The same value (the related entity's current version at write time) is recorded on the **snapshot** rows too:

- Single-cardinality → `<field>_version` column on the snapshot row.
- Collection → `target_version` column on the join table.

`null` only appears when the target is non-versioned. Pinning a versioned target at version `0` is forbidden by design; since insert seeds `1`, this case only arises if you try to assign an unpersisted versioned entity as a relation.

## `#[Version]`

Every `#[Versioned]` entity **must** declare one integer property marked `#[Version]` — the per-entity snapshot counter.

```php
use SoureCode\Component\Versionable\Attribute\Version;

#[Version]
#[ORM\Column]
private int $version = 0;

public function getVersion(): int
{
    return $this->version;
}
```

The framework increments it once per snapshot, so `getVersion()` is the snapshot count: a fresh in-memory entity is `0`, after persist it is `1`, after every subsequent edit it advances by one. It is excluded from the snapshot's own data — it is metadata, not a tracked field.

No arguments. Target: `\Attribute::TARGET_PROPERTY`.

This is **not** Doctrine's `#[ORM\Version]`, which is optimistic locking — an independent, optional concern. If you want locking too, add `#[ORM\Version]` on a *separate* field; Versionable excludes that field from snapshot content automatically (a lock counter is concurrency metadata, not tracked data) and Doctrine keeps managing it.
