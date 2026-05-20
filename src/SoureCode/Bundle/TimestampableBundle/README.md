# sourecode/timestampable-bundle

Symfony wiring for [`sourecode/timestampable`](../../Component/Timestampable/README.md). Installing the bundle is enough — entities annotated with `#[CreatedAt]` / `#[UpdatedAt]` / `#[ChangedAt]` / `#[DeletedAt]` start being maintained on flush.

## Install

Part of the `scaffold` monorepo. Symfony Flex registers the bundle automatically. No configuration block.

## Public surface

| Service id | Role |
|------------|------|
| `Psr\Clock\ClockInterface` (alias of `Symfony\Component\Clock\Clock`) | The clock the listeners use. Inject elsewhere if you need wall time in your own services. |

## Traits

One per attribute, lives under `Doctrine/`:

```php
use SoureCode\Bundle\TimestampableBundle\Doctrine\CreatedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\UpdatedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\DeletedAtTrait;

#[ORM\Entity]
class Article
{
    use CreatedAtTrait; // $createdAt + #[CreatedAt], non-nullable
    use UpdatedAtTrait; // $updatedAt + #[UpdatedAt], nullable
    use DeletedAtTrait; // $deletedAt + #[DeletedAt], nullable
}
```

`DeletedAtTrait` is a marker — filled by [`Removable`](../../Component/Removable/README.md), not by the listener.

## Behavior

See the [component README](../../Component/Timestampable/README.md).
