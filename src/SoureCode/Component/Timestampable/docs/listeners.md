# Listeners (manual wiring)

Use the [`TimestampableBundle`](../../../Bundle/TimestampableBundle/README.md) when running Symfony — it registers both listeners and the clock. This page is for plain Doctrine setups.

## `TimestampableListener`

```php
use Doctrine\ORM\Events;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\Timestampable\EventListener\TimestampableListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

$listener = new TimestampableListener(
    metadataFactory:  new TimestampableMetadataFactory(),
    timestampFactory: new TimestampFactory($clock), // Psr\Clock\ClockInterface
    changeSetMatcher: new ChangeSetMatcher(),
);

$em->getEventManager()->addEventListener([Events::prePersist, Events::onFlush], $listener);
```

Events: `prePersist`, `onFlush`.

## `TimestampableMappingListener`

```php
use Doctrine\ORM\Events;
use SoureCode\Component\Timestampable\EventListener\TimestampableMappingListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

$mappingListener = new TimestampableMappingListener(new TimestampableMetadataFactory());

$em->getEventManager()->addEventListener([Events::loadClassMetadata], $mappingListener);
```

Event: `loadClassMetadata`.

Registers `#[ORM\Column]` for any property carrying a Timestampable attribute that does not already declare one. Existing `#[ORM\Column]` is left alone. Column nullability per attribute:

| attribute | nullable column |
|-----------|------------------|
| `CreatedAt` | `false` |
| `UpdatedAt` | from `nullable:` argument |
| `ChangedAt` | `true` |
| `DeletedAt` | `true` |
