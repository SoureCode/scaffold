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

Annotate entities with `#[CreatedAt]`, `#[UpdatedAt]`, `#[ChangedAt]`, `#[DeletedAt]` — see the [component README](../../Component/Timestampable/README.md) and [docs/](../../Component/Timestampable/docs).

### Bundled traits

One trait per attribute, ships in `Doctrine/`. Mix freely.

```php
use SoureCode\Bundle\TimestampableBundle\Doctrine\CreatedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\UpdatedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\DeletedAtTrait;

#[ORM\Entity]
class Article
{
    use CreatedAtTrait; // $createdAt + #[CreatedAt] + non-null column
    use UpdatedAtTrait; // $updatedAt + #[UpdatedAt] + nullable column
    use DeletedAtTrait; // $deletedAt + #[DeletedAt] + nullable column
}
```
