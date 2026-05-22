# Attributes

All four target a property (`\Attribute::TARGET_PROPERTY`).

## `#[CreatedBy]`

```php
#[CreatedBy]
private ?User $createdBy = null;
```

Filled once on insert if a current author is available. Never overwritten.

No arguments.

## `#[UpdatedBy]`

```php
#[UpdatedBy(nullable: true)]
private ?User $updatedBy = null;
```

Filled on every change.

| arg | type | default | meaning |
|-----|------|---------|---------|
| `nullable` | `bool` | `true` | `true`: `null` until the first change. `false`: filled on insert too. |

## `#[ChangedBy]` (repeatable)

```php
#[ChangedBy(field: 'status', matchValue: true, value: Status::Published)]
private ?User $publishedBy = null;
```

Filled when one of the watched fields appears in the changeset.

| arg | type | default | meaning |
|-----|------|---------|---------|
| `field` | `string \| list<string>` | (required) | Field name, dotted path, or list. |
| `matchValue` | `bool` | `false` | Enable value matcher; required to use `value: null`. |
| `value` | `mixed` | `null` | Fires only when the new value equals this. Backed enums normalize against their scalar form. |

### Field forms

| form | meaning |
|------|---------|
| `'title'` | flat property |
| `'address.city'` | embeddable nested field |
| `'topic.title'` | relation traversal (single-card) |
| `'owner.department.code'` | multi-level relation traversal |
| `'channels.title'` | inverse-side collection traversal |
| `'tags'` | collection itself — fires on add/remove |

### Argument validation

- `field: []` → `InvalidArgumentException`.
- `matchValue: true` combined with `field: list<string>` → `InvalidArgumentException`.

## `#[DeletedBy]`

```php
#[DeletedBy]
private ?User $deletedBy = null;
```

Pure marker. The flush listener never writes to this field — [`Removable`](../../Removable/README.md) does. The mapping listener registers a nullable `ManyToOne` to the property's PHP type.

No arguments.
