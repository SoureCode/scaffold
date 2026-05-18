# Attributes

One attribute, targeting properties (`\Attribute::TARGET_PROPERTY`).

## `#[Versioned]`

```php
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
class Article
{
    #[Versioned]
    #[ORM\Column]
    private string $title;

    #[Versioned]
    #[ORM\ManyToOne(targetEntity: Author::class)]
    private ?Author $author = null;

    #[Versioned]
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;
}
```

Marks the property as **tracked**. Any change to it triggers a snapshot row and the field is mirrored on the version table.

No arguments.

### Supported property kinds

| Kind | Schema | Snapshot |
|------|--------|----------|
| Scalar / enum field | mirrored column on the version row | column value |
| `ManyToOne` (owning) | `<field>_id` column | foreign-key value |
| `OneToOne` (either side) | `<field>_id` column | foreign-key value |
| `OneToMany` (inverse collection) | separate `<source>_version_<field>` join table | one row per current element |
| `ManyToMany` | separate `<source>_version_<field>` join table | one row per current element |

### Inheritance

Bindings are collected by walking up the class hierarchy — `#[Versioned]` declared on a parent property is picked up on the child entity.

### `target_version`

If the target of a `#[Versioned]` association is itself `Versionable`, the snapshot row also records the target's current version number:

- Single-cardinality → extra `<field>_version` column on the version row.
- Collection → extra `target_version` column on the join table row.

"Current version" means the highest `version` ever written for that target, or `null` if the target has never produced a version row yet. Version numbers start at `1`; no `0` sentinel is used.
