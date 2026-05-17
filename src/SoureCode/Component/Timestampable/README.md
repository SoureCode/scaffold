# sourecode/timestampable

Automatic Doctrine timestamps via PHP attributes — created, updated, changed (with field/value matching, dotted paths, collection traversal, cycle protection).

## Install

Part of the `scaffold` monorepo — always installed with the rest. See the root [README](../../../../README.md) for the workflow.

## Quick start

```php
use SoureCode\Component\Timestampable\Attribute\CreatedAt;
use SoureCode\Component\Timestampable\Attribute\UpdatedAt;

#[ORM\Entity]
class Article
{
    #[CreatedAt]
    private ?\DateTimeInterface $createdAt = null;

    #[UpdatedAt]
    private ?\DateTimeInterface $updatedAt = null;
}
```

Wire the listeners against your `EntityManager`:

```php
$metadataFactory = new TimestampableMetadataFactory();
$timestampFactory = new TimestampFactory($clock);

$listener = new TimestampableListener($clock, $metadataFactory, $timestampFactory, new ChangeSetMatcher());
$mappingListener = new TimestampableMappingListener($metadataFactory);

$em->getEventManager()->addEventListener([Events::prePersist, Events::onFlush], $listener);
$em->getEventManager()->addEventListener([Events::loadClassMetadata], $mappingListener);
```

## Docs

- [Attributes](docs/attributes.md)
- [Listeners and wiring](docs/listeners.md)
- [Usage patterns](docs/usage.md)
