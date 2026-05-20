# Usage patterns

Recipes for the cases that aren't a single line.

## Custom property names

```php
final class Note
{
    #[CreatedAt]
    private ?\DateTimeImmutable $writtenAt = null;

    #[UpdatedAt]
    private ?\DateTimeImmutable $editedAt = null;
}
```

## Interface fallback (no attributes)

When an entity declares no Timestampable attributes but implements `TimestampableInterface`, the listener calls `setCreatedAt()` / `setUpdatedAt()` directly. Useful for legacy entities you can't decorate.

```php
final class LegacyArticle implements TimestampableInterface
{
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;
    // getters + setters
}
```

Attributes and interface fallback do not mix: presence of any Timestampable attribute disables the interface path.

## Column type vs property type

The listener writes the type that matches the **property** PHP type, not the `type:` argument:

| property type | listener writes |
|---------------|------------------|
| `\DateTimeImmutable` / `\DateTimeInterface` | `\DateTimeImmutable` |
| `\DateTime` | `\DateTime` |
| `int` | unix timestamp |

The `type:` argument only feeds the mapping listener — it picks the storage column. Use `DATETIMETZ_IMMUTABLE` (default), `DATE_IMMUTABLE`, `TIME_IMMUTABLE`, `DATETIME_IMMUTABLE`, or the mutable variants.

## `#[ChangedAt]` recipes

### Fire when a status field becomes a specific enum case

```php
#[ChangedAt(field: 'status', matchValue: true, value: Status::Published)]
private ?\DateTimeImmutable $publishedAt = null;
```

### Fire when a relation pointer is cleared

```php
#[ChangedAt(field: 'parent', matchValue: true, value: null)]
private ?\DateTimeImmutable $orphanedAt = null;
```

### Fire on any change across several fields

```php
#[ChangedAt(field: ['title', 'body'])]
private ?\DateTimeImmutable $contentChangedAt = null;
```

### Embeddable field

```php
#[ChangedAt(field: 'address.city')]
private ?\DateTimeImmutable $relocatedAt = null;
```

### Multi-level relation traversal

```php
#[ChangedAt(field: 'owner.department.code')]
private ?\DateTimeImmutable $deptCodeChangedAt = null;
```

### Inverse-side collection traversal

```php
#[ChangedAt(field: 'channels.title')]
private ?\DateTimeImmutable $lastChannelTitleChangedAt = null;
```

### Collection itself

```php
#[ChangedAt(field: 'tags')]
private ?\DateTimeImmutable $tagsChangedAt = null;
```

## Soft-delete marker

```php
#[DeletedAt]
private ?\DateTimeImmutable $deletedAt = null;
```

The flush listener never writes to `deletedAt`. Use [`Removable`](../../Removable/README.md) for the soft-delete + restore orchestration; it reads the marker through Timestampable metadata.

The [bundle](../../../Bundle/TimestampableBundle/README.md) ships a `DeletedAtTrait` already wired to `#[DeletedAt]`.

## Overriding the auto-mapping

Add `#[ORM\Column]` explicitly to override defaults (custom `name`, `length`, custom column options):

```php
#[CreatedAt]
#[ORM\Column(name: 'inserted_at', nullable: false)]
private ?\DateTimeImmutable $createdAt = null;
```
