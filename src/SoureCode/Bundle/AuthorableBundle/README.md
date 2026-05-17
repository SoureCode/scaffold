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
    user_class:       ~  # optional; forces a concrete entity class as the ManyToOne target for every binding
```

### `author_provider`

Service id implementing `SoureCode\Component\Authorable\Author\AuthorProviderInterface`. Left null, defaults to `SecurityAuthorProvider` (wraps `Security::getUser()`). If `symfony/security-bundle` is not installed and `author_provider` is left null, the bundle throws on boot.

```yaml
authorable:
    author_provider: App\Authorable\CurrentUserProvider
```

### `user_class`

Set this when your entities type author properties as an **interface** (e.g. the bundled `AuthorableTrait` types them as `Symfony\Component\Security\Core\User\UserInterface`). Doctrine needs a concrete class for the FK; the mapping listener uses `user_class` instead of the property's PHP type.

```yaml
authorable:
    user_class: App\Entity\User
```

Leave it null when your entities type author properties directly with the concrete class — the property type is then used as-is.

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

### Bundled `AuthorableTrait`

For the common Symfony Security case, drop this trait into your entity:

```php
use SoureCode\Bundle\AuthorableBundle\Doctrine\AuthorableTrait;

#[ORM\Entity]
class Article
{
    use AuthorableTrait;
}
```

The trait declares `createdBy`/`updatedBy` typed as `Symfony\Component\Security\Core\User\UserInterface` and carries the `#[CreatedBy]` + `#[UpdatedBy]` attributes. Set `user_class:` in your bundle config so the mapping listener knows which concrete entity to use as the FK target.
