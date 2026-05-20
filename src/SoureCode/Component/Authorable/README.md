# sourecode/authorable

Track who created, updated, changed, or deleted a Doctrine entity. Mark a property with `#[CreatedBy]` / `#[UpdatedBy]` / `#[ChangedBy]` / `#[DeletedBy]`; the property is maintained automatically from a pluggable "current author" source.

## When to use

You want "who" tracking on entities without hand-written setters at every write site.

## When not to use

You need a full per-field audit log. This is single-row state — pair with [`Versionable`](../Versionable/README.md) for history.

## Install

Part of the `scaffold` monorepo. The [`authorable-bundle`](../../Bundle/AuthorableBundle/README.md) wires everything; without it, see [`docs/listeners.md`](docs/listeners.md).

## Minimal example

```php
#[ORM\Entity]
class Article
{
    #[CreatedBy]
    private ?User $createdBy = null;

    #[UpdatedBy]
    private ?User $updatedBy = null;
}
```

`createdBy` fills once. `updatedBy` follows every change.

## Reference

- [Attributes](docs/attributes.md) — full per-attribute reference.
- [Listeners](docs/listeners.md) — manual wiring (skip if using the bundle).
- [Usage patterns](docs/usage.md) — author providers, interface fallback, recipes.

## Behavior

| Attribute | Filled |
|-----------|--------|
| `#[CreatedBy]` | On insert. Never overwritten. |
| `#[UpdatedBy]` | On every change. `null` until the first change (override with `nullable: false`). |
| `#[ChangedBy]` | On every change that touches a watched field. |
| `#[DeletedBy]` | Never by this package — filled by [`Removable`](../Removable/README.md) on soft delete. |

When no current author is available (anonymous, CLI, worker), the property is left untouched.

## Composition

- [`Timestampable`](../Timestampable/README.md) — same lifecycle on the "when" side.
- [`Removable`](../Removable/README.md) — fills `#[DeletedBy]` and clears it on restore.
- [`Versionable`](../Versionable/README.md) — mark a `#[CreatedBy]`/`#[UpdatedBy]` property `#[Versioned]` and every change captures the author.

## Limits

- `AuthorProviderInterface::getCurrentAuthor()` must return either a Doctrine-managed entity or `null`.
- `#[ChangedBy]` with `field: []` or `matchValue: true` combined with multiple fields → `InvalidArgumentException`.

## Stability

Attribute names, arguments, defaults, and observable behavior are stable. The provider contract is stable.
