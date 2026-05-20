# sourecode/timestampable

Automatic Doctrine timestamps via PHP attributes. Mark a property with `#[CreatedAt]` / `#[UpdatedAt]` / `#[ChangedAt]` and the value is maintained automatically. `#[DeletedAt]` is the soft-delete marker companion — filled by [`Removable`](../Removable/README.md), never by this package.

## When to use

Any entity that needs lifecycle timestamps without hand-written setters.

## When not to use

You want full history per field. Pair with [`Versionable`](../Versionable/README.md).

## Install

Part of the `scaffold` monorepo. The [`timestampable-bundle`](../../Bundle/TimestampableBundle/README.md) wires everything; without it, see [`docs/listeners.md`](docs/listeners.md).

## Minimal example

```php
#[ORM\Entity]
class Article
{
    #[CreatedAt]
    private ?\DateTimeImmutable $createdAt = null;

    #[UpdatedAt]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

`createdAt` fills once. `updatedAt` follows every change.

## Reference

- [Attributes](docs/attributes.md) — full per-attribute reference.
- [Listeners](docs/listeners.md) — manual wiring (skip if using the bundle).
- [Usage patterns](docs/usage.md) — interface fallback, change-tracking forms, column type choices.

## Behavior

| Attribute | Filled |
|-----------|--------|
| `#[CreatedAt]` | On insert. Never overwritten. |
| `#[UpdatedAt]` | On every change. `null` until the first change (override with `nullable: false`). |
| `#[ChangedAt]` | On every change that touches a watched field. |
| `#[DeletedAt]` | Never by this package — filled by [`Removable`](../Removable/README.md) on soft delete. |

## Composition

- [`Authorable`](../Authorable/README.md) — same lifecycle on the "who" side.
- [`Removable`](../Removable/README.md) — soft-delete orchestration that fills `#[DeletedAt]`.
- [`Versionable`](../Versionable/README.md) — snapshot timestamps by marking them `#[Versioned]`.

## Limits

- `#[ChangedAt]` with `field: []` or `matchValue: true` combined with multiple fields → `InvalidArgumentException`.

## Stability

Attribute names, arguments, defaults, and observable behavior are stable.
