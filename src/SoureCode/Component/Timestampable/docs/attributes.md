# Attributes

All four attributes target a property (`\Attribute::TARGET_PROPERTY`).

## `#[CreatedAt]`

```php
#[CreatedAt(type: Types::DATETIMETZ_IMMUTABLE)]
private ?\DateTimeInterface $createdAt = null;
```

Set once on `prePersist` if the property is `null`. Never overwritten.

**Arguments**

| name | type | default | meaning |
|------|------|---------|---------|
| `type` | string | `DATETIMETZ_IMMUTABLE` | Doctrine column type, used by the mapping listener |

## `#[UpdatedAt]`

```php
#[UpdatedAt(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
private ?\DateTimeInterface $updatedAt = null;
```

Refreshed on every flush of the entity. By default the property stays `null` until the first real update — pass `nullable: false` to set it on initial persist as well.

**Arguments**

| name | type | default | meaning |
|------|------|---------|---------|
| `type` | string | `DATETIMETZ_IMMUTABLE` | column type |
| `nullable` | bool | `true` | When `true` (default), stays `null` until first real update; column nullable. Pass `false` to fill on persist. |

## `#[ChangedAt]` (repeatable)

```php
#[ChangedAt(field: 'status', matchValue: true, value: Status::Published)]
private ?\DateTimeImmutable $publishedAt = null;
```

Set when one of the watched fields appears in the changeset.

**Arguments**

| name | type | default | meaning |
|------|------|---------|---------|
| `field` | `string \| list<string>` | (required) | Field name, dotted path, or list of those |
| `matchValue` | bool | `false` | Enable value matcher; required to use `value: null` |
| `value` | mixed | `null` | Only fires when the new value equals this. Backed enums normalize against their scalar form. |
| `type` | string | `DATETIMETZ_IMMUTABLE` | column type |

### Field forms

| form | meaning |
|------|---------|
| `'title'` | flat property on the entity |
| `'address.city'` | embeddable nested field |
| `'topic.title'` | relation traversal |
| `'owner.department.code'` | multi-level relation traversal |
| `'channels.title'` | inverse-side collection traversal — fires when any element's `title` changes |
| `'tags'` | collection itself — fires on add/remove |

### Validation rules

- `field: []` → `InvalidArgumentException`
- `matchValue: true` with multiple fields → `InvalidArgumentException`

## `#[DeletedAt]`

```php
#[DeletedAt(type: Types::DATETIMETZ_IMMUTABLE)]
private ?\DateTimeImmutable $deletedAt = null;
```

**Pure marker.** The flush listener never fills this field — the caller assigns it (typically through a soft-remove helper such as the `Removable` component). The mapping listener auto-registers a nullable column of the given Doctrine type when no `#[ORM\Column]` is declared.

**Arguments**

| name | type | default | meaning |
|------|------|---------|---------|
| `type` | string | `DATETIMETZ_IMMUTABLE` | column type, used by the mapping listener |
