# Authorable

Doctrine author-tracking driven by PHP attributes. Stores `ManyToOne` references to whoever performed the change.

## Contents

- [Attributes](attributes.md) — `#[CreatedBy]`, `#[UpdatedBy]`, `#[ChangedBy]`, `#[DeletedBy]`
- [Listeners](listeners.md) — `AuthorableListener`, `AuthorableMappingListener`
- [Usage](usage.md) — author provider, interface fallback, change tracking

## Concepts

| Concept | Purpose |
|---------|---------|
| `#[CreatedBy]` | Set once on `prePersist` if the current author is available |
| `#[UpdatedBy]` | Refresh on every flush; defaults to `nullable: true` (stays `null` until first real update) |
| `#[ChangedBy]` | Set when a watched field is in the changeset (supports dotted paths, value matcher, collections) |
| `#[DeletedBy]` | Marker only — association is auto-mapped, the caller fills the value (e.g. via `Removable`) |
| `AuthorProviderInterface` | The application-supplied source for "current author" (typically wraps Symfony Security) |
| `AuthorableInterface` | Optional contract with `getCreatedBy()`/`setCreatedBy()` etc., used when no attributes are present |
| Auto-mapping | Listener registers `ManyToOne(targetEntity: <property type>)` if `#[ORM\ManyToOne]` is missing |
