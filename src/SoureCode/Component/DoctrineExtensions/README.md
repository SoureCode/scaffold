# sourecode/doctrine-extensions

Shared building blocks for SoureCode's attribute-driven Doctrine behavior packages (`sourecode/timestampable`, `sourecode/authorable`, …).

Provides the contracts, the changeset matcher, and the `onFlush` orchestration. Doesn't ship any user-facing attributes on its own.

## Install

Part of the `scaffold` monorepo — always installed with the rest.

## Provided

| Layer | Class / interface |
|-------|-------------------|
| Binding contracts | `Metadata\PersistBindingInterface`, `UpdateBindingInterface`, `ChangeBindingInterface` |
| Metadata contracts | `Metadata\BehaviorMetadataInterface`, `BehaviorMetadataFactoryInterface` |
| Changeset evaluation | `ChangeSet\ChangeSetMatcher` |
| Listener orchestration | `EventListener\AbstractFlushListener` |

## Docs

- [Architecture](docs/architecture.md)
- [Extending](docs/extending.md)
