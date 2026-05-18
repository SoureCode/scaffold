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

Set this when your entities type author properties as an **interface** (e.g. the bundled `CreatedByTrait` / `UpdatedByTrait` / `DeletedByTrait` type them as `Symfony\Component\Security\Core\User\UserInterface`). Doctrine needs a concrete class for the FK; the mapping listener uses `user_class` instead of the property's PHP type.

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

### Bundled traits

One trait per attribute, ships in `Doctrine/`. All typed against `Symfony\Component\Security\Core\User\UserInterface`. Mix freely. Set `user_class:` in the bundle config so the mapping listener knows which concrete entity to use as the FK target.

```php
use SoureCode\Bundle\AuthorableBundle\Doctrine\CreatedByTrait;
use SoureCode\Bundle\AuthorableBundle\Doctrine\UpdatedByTrait;
use SoureCode\Bundle\AuthorableBundle\Doctrine\DeletedByTrait;

#[ORM\Entity]
class Article
{
    use CreatedByTrait; // $createdBy + #[CreatedBy]
    use UpdatedByTrait; // $updatedBy + #[UpdatedBy]
    use DeletedByTrait; // $deletedBy + #[DeletedBy]
}
```

`DeletedByTrait` is a pure marker — the field is filled by [`Removable`](../../Component/Removable/docs/index.md), not by the flush listener.
