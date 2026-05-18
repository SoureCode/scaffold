# Timestampable

Doctrine timestamp automation driven by PHP attributes.

## Contents

- [Attributes](attributes.md) — `#[CreatedAt]`, `#[UpdatedAt]`, `#[ChangedAt]`, `#[DeletedAt]`
- [Listeners](listeners.md) — `TimestampableListener`, `TimestampableMappingListener`
- [Usage](usage.md) — trait, interface, bare attributes, types, paths, collections

## Concepts

| Concept | Purpose |
|---------|---------|
| `#[CreatedAt]` | Set on `prePersist` if null |
| `#[UpdatedAt]` | Refresh on every flush; defaults to `nullable: true` (stays null until first real update). Pass `nullable: false` to fill on persist. |
| `#[ChangedAt]` | Set when a watched field is in the changeset (supports value match, dotted paths, collections) |
| `#[DeletedAt]` | Marker only — column is auto-mapped, the caller fills the value (e.g. via `Removable`) |
| `TimestampableInterface` | Optional contract used as interface fallback when no attributes are present |
| `CreatedAtTrait` / `UpdatedAtTrait` / `DeletedAtTrait` | Pre-baked convenience traits — one per attribute, ship in [`TimestampableBundle`](../../../Bundle/TimestampableBundle/README.md) |
| Auto-column mapping | Listener registers the Doctrine `Column` when missing |
