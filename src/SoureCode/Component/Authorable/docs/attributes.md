# Attributes

All four target a property (`\Attribute::TARGET_PROPERTY`).

## `#[CreatedBy]`

```php
#[CreatedBy]
private ?User $createdBy = null;
```

Set on `prePersist` if the property is `null` **and** a current author is available. Never overwritten.

No arguments.

## `#[UpdatedBy]`

```php
#[UpdatedBy(nullable: true)]
private ?User $updatedBy = null;
```

Refreshed on every flush when a current author is available. Defaults to `nullable: true` (stays `null` until the first real update). Pass `nullable: false` to fill on initial persist as well.

**Arguments**

| name | type | default | meaning |
|------|------|---------|---------|
| `nullable` | bool | `true` | When `true`, skip on `prePersist`; column becomes nullable in auto-mapping. |

## `#[ChangedBy]` (repeatable)

```php
#[ChangedBy(field: 'status', matchValue: true, value: Status::Published)]
private ?User $publishedBy = null;
```

Set when one of the watched fields appears in the entity's changeset.

**Arguments**

| name | type | default | meaning |
|------|------|---------|---------|
| `field` | `string \| list<string>` | (required) | Flat property, dotted path, or list of those |
| `matchValue` | bool | `false` | Enable value matcher; required for `value: null` |
| `value` | mixed | `null` | Only fires when the new value equals this. Backed enums normalize against their scalar form. |

### Field forms

| form | meaning |
|------|---------|
| `'title'` | flat property on the entity |
| `'address.city'` | embeddable nested field |
| `'topic.title'` | relation traversal |
| `'owner.department.code'` | multi-level relation traversal |
| `'channels.title'` | inverse-side collection traversal |
| `'tags'` | collection itself — fires on add/remove |

### Validation

- `field: []` → `InvalidArgumentException`
- `matchValue: true` with multiple fields → `InvalidArgumentException`

## `#[DeletedBy]`

```php
#[DeletedBy]
private ?User $deletedBy = null;
```

**Pure marker.** The flush listener never fills this field — the caller assigns it (typically through a soft-remove helper such as the `Removable` component). The mapping listener auto-registers a nullable `ManyToOne` to the property's PHP type when no `#[ORM\ManyToOne]` is declared.

No arguments.
