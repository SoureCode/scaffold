# sourecode/versionable-bundle

Symfony bundle wiring for [`sourecode/versionable`](../../Component/Versionable/README.md).

## Install

Part of the `scaffold` monorepo — always installed with the rest.

Symfony Flex registers the bundle (and its prerequisites `DoctrineBundle` + `DoctrineExtensionsBundle`) automatically. No configuration block.

## Services registered

| Service id | Tagged event |
|-----------|--------------|
| `VersionableMetadataFactory` | — |
| `ClockInterface` (`Symfony\Component\Clock\Clock`) | — |
| `VersionableListener` | `doctrine.event_listener` (`onFlush`) |
| `VersionableSchemaListener` | `doctrine.event_listener` (`postGenerateSchema`) |

## Usage

Annotate properties with `#[Versioned]` — see the [component README](../../Component/Versionable/README.md).

Inject `VersionerInterface` (or `Versioner`) where you want to read history or revert an entity. See the component README for the full API.
