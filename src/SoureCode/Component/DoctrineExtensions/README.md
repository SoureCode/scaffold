# sourecode/doctrine-extensions

Shared primitives for SoureCode's attribute-driven Doctrine behavior packages. Provides the binding/metadata contracts, the changeset matcher, and a flush-listener template so each `*-able` package can declare *what* to track and inherit *how* to react.

## When to use

Building a new attribute-driven Doctrine behavior. See [`docs/extending.md`](docs/extending.md).

## When not to use

Consuming an existing behavior. Use Authorable, Timestampable, Removable, or Versionable from this monorepo — they already build on this layer.

## Install

Part of the `scaffold` monorepo.

## Public surface

| Layer | Contract |
|-------|----------|
| Bindings | `Metadata\PersistBindingInterface`, `UpdateBindingInterface`, `ChangeBindingInterface` |
| Metadata | `Metadata\BehaviorMetadataInterface`, `BehaviorMetadataFactoryInterface` |
| Changeset | `ChangeSet\ChangeSetMatcher` |
| Listener | `EventListener\AbstractFlushListener` |

## Stability

Public contracts are stable.
