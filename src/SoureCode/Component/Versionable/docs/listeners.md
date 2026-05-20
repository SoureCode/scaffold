# Listeners (manual wiring)

Use the [`VersionableBundle`](../../../Bundle/VersionableBundle/README.md) when running Symfony. This page is for plain Doctrine setups.

## `VersionableListener`

```php
use Doctrine\ORM\Events;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

$listener = new VersionableListener(
    metadataFactory: new VersionableMetadataFactory(),
    clock:           $clock, // Psr\Clock\ClockInterface
);

$em->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], $listener);
```

Events: `onFlush`, `postFlush`.

## `VersionableSchemaListener`

```php
use Doctrine\ORM\Tools\ToolEvents;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

$schemaListener = new VersionableSchemaListener(new VersionableMetadataFactory());

$em->getEventManager()->addEventListener([ToolEvents::postGenerateSchema], $schemaListener);
```

Event: `postGenerateSchema`.

Adds the `<entity>_version` table (and any `<entity>_version_<field>` join tables) to the schema for every Doctrine class that carries at least one `#[Versioned]` property. Scalar column options (`length`, `precision`, `scale`, `enumType`, `notnull`) are copied from the source mapping.

## `Versioner`

```php
use SoureCode\Component\Versionable\Versioner;

$versioner = new Versioner(
    entityManager:   $em,
    metadataFactory: new VersionableMetadataFactory(),
    logger:          $logger, // Psr\Log\LoggerInterface — optional, defaults to NullLogger
);
```

See [usage patterns](usage.md) for the API.
