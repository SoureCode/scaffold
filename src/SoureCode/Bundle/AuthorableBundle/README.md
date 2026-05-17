# sourecode/authorable-bundle

Symfony bundle wiring for [`sourecode/authorable`](../../Component/Authorable/README.md).

## Install

Part of the `scaffold` monorepo — always installed with the rest.

Optional: `symfony/security-bundle` for the bundled default `SecurityAuthorProvider`.

Symfony Flex registers the bundle (and its prerequisites `DoctrineBundle` + `DoctrineExtensionsBundle`) automatically.

## Configuration

```yaml
authorable:
    author_provider: ~   # optional; defaults to SecurityAuthorProvider when symfony/security-bundle is installed
```

To use your own author source, point `author_provider:` at a service implementing `SoureCode\Component\Authorable\Author\AuthorProviderInterface`:

```yaml
authorable:
    author_provider: App\Authorable\CurrentUserProvider
```

If `symfony/security-bundle` is **not** installed and `author_provider` is left null, the bundle throws on boot.

## Services registered

| Service id | Tagged event |
|-----------|--------------|
| `AuthorableMetadataFactory` | — |
| `AuthorableListener` | `doctrine.event_listener` (`prePersist`, `onFlush`) |
| `AuthorableMappingListener` | `doctrine.event_listener` (`loadClassMetadata`) |
| `AuthorProviderInterface` (alias) | — points to your `author_provider` service |
| `SecurityAuthorProvider` *(when default is used)* | — |

## Usage

Annotate entities with `#[CreatedBy]`, `#[UpdatedBy]`, `#[ChangedBy]` — see the [component README](../../Component/Authorable/README.md) and [docs/](../../Component/Authorable/docs).
