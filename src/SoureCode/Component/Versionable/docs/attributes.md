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

Marks the entity as versioned. Every mapped field and association — except the identifier — is tracked; any change to one of them appends a snapshot row.

No arguments.

Target: `\Attribute::TARGET_CLASS`. Mark the root of an inheritance hierarchy once; subclasses are versioned too.

### How each field kind is stored

| Kind | Where it lives |
|------|----------------|
| Scalar / enum field | column on the snapshot row |
| Embeddable | flattened columns on the snapshot row |
| `ManyToOne` (owning) | `<field>_id` column on the snapshot row |
| `OneToOne` (either side) | `<field>_id` column on the snapshot row |
| `OneToMany` (inverse collection) | one row per element in `<entity>_version_<field>` |
| `ManyToMany` | one row per element in `<entity>_version_<field>` |

### `target_version`

If the target of a versioned relation is itself `Versionable`, the snapshot also records the target's current version number:

- Single-cardinality → `<field>_version` column on the snapshot row.
- Collection → `target_version` column on the join table.

`null` means the target had never produced a version row at snapshot time. Version numbers start at `1`; no `0` sentinel.

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

The framework increments it once per snapshot, so `getVersion()` is the snapshot count: a fresh entity is `0`, its first snapshot is `1`. It is excluded from the snapshot's own data — it is metadata, not a tracked field.

No arguments. Target: `\Attribute::TARGET_PROPERTY`.

This is **not** Doctrine's `#[ORM\Version]`, which is optimistic locking — an independent, optional concern. If you want locking too, add `#[ORM\Version]` on a *separate* field; Versionable excludes that field from snapshot content automatically (a lock counter is concurrency metadata, not tracked data) and Doctrine keeps managing it.
