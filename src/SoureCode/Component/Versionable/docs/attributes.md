# Attributes

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

Marks the property as tracked. Any change to it appends a snapshot row.

No arguments.

Target: `\Attribute::TARGET_PROPERTY`. Picked up from parent classes too.

### Supported property kinds

| Kind | Where it lives |
|------|----------------|
| Scalar / enum field | column on the snapshot row |
| `ManyToOne` (owning) | `<field>_id` column on the snapshot row |
| `OneToOne` (either side) | `<field>_id` column on the snapshot row |
| `OneToMany` (inverse collection) | one row per element in `<entity>_version_<field>` |
| `ManyToMany` | one row per element in `<entity>_version_<field>` |

### `target_version`

If the target of a versioned relation is itself `Versionable`, the snapshot also records the target's current version number:

- Single-cardinality → `<field>_version` column on the snapshot row.
- Collection → `target_version` column on the join table.

`null` means the target had never produced a version row at snapshot time. Version numbers start at `1`; no `0` sentinel.
