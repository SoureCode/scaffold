# Timestampable

Doctrine timestamp automation driven by PHP attributes.

## Contents

- [Attributes](attributes.md) — `#[CreatedAt]`, `#[UpdatedAt]`, `#[ChangedAt]`
- [Listeners](listeners.md) — `TimestampableListener`, `TimestampableMappingListener`
- [Usage](usage.md) — trait, interface, bare attributes, types, paths, collections

## Concepts

| Concept | Purpose |
|---------|---------|
| `#[CreatedAt]` | Set on `prePersist` if null |
| `#[UpdatedAt]` | Refresh on every flush; defaults to `nullable: true` (stays null until first real update). Pass `nullable: false` to fill on persist. |
| `#[ChangedAt]` | Set when a watched field is in the changeset (supports value match, dotted paths, collections) |
| `TimestampableInterface` + `TimestampableTrait` | Pre-baked opt-in for the common `createdAt`/`updatedAt` pair |
| Auto-column mapping | Listener registers the Doctrine `Column` when missing |
