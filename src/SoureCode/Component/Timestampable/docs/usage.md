# Usage patterns

Two valid opt-in styles at the component layer: attributes or interface fallback. Per-attribute convenience traits (`CreatedAtTrait`, `UpdatedAtTrait`, `DeletedAtTrait`) live in the [bundle](../../../Bundle/TimestampableBundle/README.md).

## 1. Interface fallback (no attributes)

Implement `TimestampableInterface` with your own setters. The listener falls back to interface calls only when **no** attributes are present on the entity.

```php
final class LegacyArticle implements TimestampableInterface
{
    private ?\DateTimeInterface $createdAt = null;
    private ?\DateTimeInterface $updatedAt = null;

    // getters + setters
}
```

## 2. Bare attributes (custom property names)

```php
final class Note
{
    #[CreatedAt]
    private ?\DateTimeImmutable $writtenAt = null;

    #[UpdatedAt]
    private ?\DateTimeImmutable $editedAt = null;
}
```

## Column type choices

| column type | property type | listener writes |
|-------------|---------------|-----------------|
| `datetimetz_immutable` (default) | `\DateTimeImmutable` / `\DateTimeInterface` | `\DateTimeImmutable` |
| `datetime_immutable`, `date_immutable`, `time_immutable` | same | `\DateTimeImmutable` (DB truncates) |
| `datetime`, `datetimetz`, `date`, `time` (mutable) | `\DateTime` | `\DateTime` |
| `integer` | `int` | unix timestamp |

Listener picks the runtime type from the **property's PHP type**, not from `type:` argument.

## Change-tracking examples

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

### Fire on any change to one of several fields
```php
#[ChangedAt(field: ['title', 'body'])]
private ?\DateTimeImmutable $contentChangedAt = null;
```

### Embeddable
```php
#[ChangedAt(field: 'address.city')]
private ?\DateTimeImmutable $relocatedAt = null;
```

### Relation traversal (multi-level)
```php
#[ChangedAt(field: 'owner.department.code')]
private ?\DateTimeImmutable $deptCodeChangedAt = null;
```

### Inverse-side collection
```php
#[ChangedAt(field: 'channels.title')]
private ?\DateTimeImmutable $lastChannelTitleChangedAt = null;
```

### Collection itself (add/remove)
```php
#[ChangedAt(field: 'tags')]
private ?\DateTimeImmutable $tagsChangedAt = null;
```

## Soft-delete marker

```php
#[DeletedAt]
private ?\DateTimeImmutable $deletedAt = null;
```

`#[DeletedAt]` is a pure marker. The flush listener never touches it — the caller assigns the value. Mapping listener registers a nullable column matching the configured `type:`.

Soft-remove orchestration (set `deletedAt = $clock->now()`, set author, flush) lives in the [`Removable`](../../Removable/docs/index.md) component, which reads the marker via Timestampable metadata.

A ready-made `DeletedAtTrait` ships in the [bundle](../../../Bundle/TimestampableBundle/README.md).

## Auto-mapping vs explicit `#[ORM\Column]`

With the mapping listener wired you can omit `#[ORM\Column]` — the listener registers a column from the attribute's `type` argument. Add `#[ORM\Column]` only when you need to override (`name`, `length`, `unique`, custom column options).
