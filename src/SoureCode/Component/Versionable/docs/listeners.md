# Listeners (manual wiring)

Use the [`VersionableBundle`](../../../Bundle/VersionableBundle/README.md) when running Symfony. This page is for plain Doctrine setups.

## `VersionableListener`

```php
use Doctrine\ORM\Events;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\Internal\PinMaintainer;
use SoureCode\Component\Versionable\Internal\SnapshotTargetResolver;
use SoureCode\Component\Versionable\Internal\SnapshotWriter;
use SoureCode\Component\Versionable\Internal\VersionIncrementer;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

$factory = new VersionableMetadataFactory();

$listener = new VersionableListener(
    new SnapshotTargetResolver($factory),
    new VersionIncrementer($factory),
    new SnapshotWriter($factory, $clock), // $clock: Psr\Clock\ClockInterface
    new PinMaintainer($factory),
);

$em->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], $listener);
```

Events: `onFlush` (resolves targets, increments versions), `postFlush` (writes snapshot rows and the live-side `<field>_version` pins).

## `VersionableSchemaListener`

```php
use Doctrine\ORM\Tools\ToolEvents;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

$schemaListener = new VersionableSchemaListener(new VersionableMetadataFactory());

$em->getEventManager()->addEventListener([ToolEvents::postGenerateSchema], $schemaListener);
```

Event: `postGenerateSchema`.

Adds the `<entity>_version` table (and any `<entity>_version_<field>` join tables) to the schema for every Doctrine class marked `#[Versioned]`, and adds the `<field>_version` pin columns to the live owning tables for single-valued versioned relations. Scalar column options (`length`, `precision`, `scale`, `enumType`, `notnull`) are copied from the source mapping.

## `VersionableClassMetadataListener`

Required if you want `$entity->get<Field>History()` methods on entities returned by the EntityManager.

```php
use Doctrine\ORM\Events;
use SoureCode\Component\Versionable\EventListener\VersionableClassMetadataListener;
use SoureCode\Component\Versionable\Internal\History\EntityProxyFactory;
use SoureCode\Component\Versionable\Internal\History\EntityProxyGenerator;
use SoureCode\Component\Versionable\Internal\History\HistoryClassFactory;
use SoureCode\Component\Versionable\Internal\History\HistoryClassGenerator;
use SoureCode\Component\Versionable\Internal\History\HistoryHydrator;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

$factory = new VersionableMetadataFactory();
$cacheDir = sys_get_temp_dir() . '/sourecode-versionable';

$historyClassFactory = new HistoryClassFactory(new HistoryClassGenerator($factory, $em), $cacheDir);
$hydrator = new HistoryHydrator($em, $factory, $historyClassFactory);
$entityProxyFactory = new EntityProxyFactory(new EntityProxyGenerator($factory), $cacheDir);

$em->getEventManager()->addEventListener(
    [Events::loadClassMetadata],
    new VersionableClassMetadataListener($entityProxyFactory, $factory, $hydrator),
);
```

Event: `loadClassMetadata`. The listener swaps the reflection class on each versioned `ClassMetadata` to the runtime-generated proxy subclass, substitutes the entity instantiator so `newInstance()` produces a proxy instance, and registers the proxy FQCN as an alias in the metadata factory so UoW lookups by `$entity::class` still resolve to the user's `ClassMetadata`.

It also lazily binds `HistoryRegistry` the first time it fires — the static lookup table the generated proxy methods call into to resolve pinned `*History` instances.

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

## Listener ordering

`loadClassMetadata` and `postGenerateSchema` run only at boot / schema generation. `onFlush` and `postFlush` run on every flush. The bundle pins them all at a single priority; manual setups don't need a particular order between Versionable's own listeners, but if you stack other Doctrine listeners that mutate `ClassMetadata` or write to the snapshot tables, ensure those run before / after Versionable as appropriate for your contract.
