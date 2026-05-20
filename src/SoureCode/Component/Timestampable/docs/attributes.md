# Attributes

All four target a property (`\Attribute::TARGET_PROPERTY`).

## `#[CreatedAt]`

```php
#[CreatedAt(type: Types::DATETIMETZ_IMMUTABLE)]
private ?\DateTimeImmutable $createdAt = null;
```

Set on `prePersist` if the property is `null`. Never overwritten.

| arg | type | default | meaning |
|-----|------|---------|---------|
| `type` | `string` | `DATETIMETZ_IMMUTABLE` | Doctrine column type used by the mapping listener when no `#[ORM\Column]` is present. |

## `#[UpdatedAt]`

```php
#[UpdatedAt(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
private ?\DateTimeImmutable $updatedAt = null;
```

Refreshed on every flush that touches the entity.

| arg | type | default | meaning |
|-----|------|---------|---------|
| `type` | `string` | `DATETIMETZ_IMMUTABLE` | column type |
| `nullable` | `bool` | `true` | `true`: stays `null` until first real update (nullable column). `false`: filled on `prePersist` too (non-nullable column). |

## `#[ChangedAt]` (repeatable)

```php
#[ChangedAt(field: 'status', matchValue: true, value: Status::Published)]
private ?\DateTimeImmutable $publishedAt = null;
```

Set when one of the watched fields appears in the changeset.

| arg | type | default | meaning |
|-----|------|---------|---------|
| `field` | `string \| list<string>` | (required) | Field name, dotted path, or list of those. |
| `matchValue` | `bool` | `false` | Enable value matcher; required to use `value: null`. |
| `value` | `mixed` | `null` | Only fires when the new value equals this. Backed enums normalize against their scalar form. |
| `type` | `string` | `DATETIMETZ_IMMUTABLE` | column type |

### Field forms

| form | meaning |
|------|---------|
| `'title'` | flat property on the entity |
| `'address.city'` | embeddable nested field |
| `'topic.title'` | relation traversal (single-card) |
| `'owner.department.code'` | multi-level relation traversal |
| `'channels.title'` | inverse-side collection traversal |
| `'tags'` | collection itself — fires on add/remove |

### Argument validation

- `field: []` → `InvalidArgumentException`.
- `matchValue: true` combined with `field: list<string>` → `InvalidArgumentException`.

## `#[DeletedAt]`

```php
#[DeletedAt(type: Types::DATETIMETZ_IMMUTABLE)]
private ?\DateTimeImmutable $deletedAt = null;
```

Pure marker. The flush listener never writes to this field — the caller does ([`Removable`](../../Removable/README.md) is the orchestrator). The mapping listener registers the column with `nullable: true`.

| arg | type | default | meaning |
|-----|------|---------|---------|
| `type` | `string` | `DATETIMETZ_IMMUTABLE` | column type |
