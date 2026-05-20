# sourecode/authorable-bundle

Symfony wiring for [`sourecode/authorable`](../../Component/Authorable/README.md). Installing the bundle is enough — entities annotated with `#[CreatedBy]` / `#[UpdatedBy]` / `#[ChangedBy]` / `#[DeletedBy]` start being maintained on flush, sourced from a default `SecurityAuthorProvider`.

## Install

Part of the `scaffold` monorepo. Symfony Flex registers the bundle automatically.

## Configuration

```yaml
authorable:
    author_provider: ~   # service id; defaults to SecurityAuthorProvider
    user_class: ~        # concrete user entity class; used when entities type author properties as an interface
```

| key | default | meaning |
|-----|---------|---------|
| `author_provider` | `SecurityAuthorProvider` | Service implementing `AuthorProviderInterface`. The default wraps `Symfony\Bundle\SecurityBundle\Security::getUser()`. |
| `user_class` | `null` | Set when entities type author properties as an **interface** (e.g. `UserInterface`). The mapping listener uses this concrete class as the `ManyToOne` target instead of the property type. |

### Override the provider

```yaml
authorable:
    author_provider: App\Authorable\CurrentUserProvider
```

### Use an interface in your entities

```yaml
authorable:
    user_class: App\Entity\User
```

## Public surface

| Service id | Role |
|------------|------|
| `SoureCode\Component\Authorable\Author\AuthorProviderInterface` | Alias to your `author_provider`. Inject to read the current author yourself. |

## Traits

One per attribute, lives under `Doctrine/`, typed against `Symfony\Component\Security\Core\User\UserInterface`. Set `user_class` so the mapping listener knows which concrete entity to wire as the `ManyToOne` target.

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

`DeletedByTrait` is a marker — filled by [`Removable`](../../Component/Removable/README.md), not by the listener.

## Behavior

See the [component README](../../Component/Authorable/README.md).
