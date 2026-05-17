# sourecode/timestampable-bundle

Symfony bundle wiring for [`sourecode/timestampable`](../../Component/Timestampable/README.md).

## Install

Part of the `scaffold` monorepo — always installed with the rest.

Symfony Flex registers the bundle (and its prerequisites `DoctrineBundle` + `DoctrineExtensionsBundle`) automatically. No configuration block.

## Services registered

| Service id | Tagged event |
|-----------|--------------|
| `TimestampableMetadataFactory` | — |
| `TimestampFactory` | — |
| `ClockInterface` (`Symfony\Component\Clock\Clock`) | — |
| `TimestampableListener` | `doctrine.event_listener` (`prePersist`, `onFlush`) |
| `TimestampableMappingListener` | `doctrine.event_listener` (`loadClassMetadata`) |

## Usage

Annotate entities with `#[CreatedAt]`, `#[UpdatedAt]`, `#[ChangedAt]` — see the [component README](../../Component/Timestampable/README.md) and [docs/](../../Component/Timestampable/docs).
