# Listeners

Two listeners. Both opt-in — wire them yourself (the bundle is not yet provided).

## `VersionableListener`

Events: `onFlush`, `postFlush`.

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

Behavior:

- `onFlush` collects snapshot targets by inspecting:
  - scheduled entity updates whose changeset touches at least one `#[Versioned]` field;
  - scheduled collection updates / deletions whose owner has a matching `#[Versioned]` collection;
  - scheduled child insertions/deletions, walking back via `mappedBy` to a `Versionable` inverse owner.
  - Owners that are themselves scheduled for deletion are excluded.
- `postFlush` writes one snapshot row per collected target. Writing in `postFlush` ensures newly-inserted collection elements already have ids.

Version numbering uses `SELECT MAX(version) + 1` per entity. The unique index on `(entity_id, version)` rejects conflicting concurrent writes; no automatic retry is built in.

## `VersionableSchemaListener`

Event: `postGenerateSchema`.

```php
use Doctrine\ORM\Tools\ToolEvents;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;

$schemaListener = new VersionableSchemaListener($metadataFactory);
$em->getEventManager()->addEventListener([ToolEvents::postGenerateSchema], $schemaListener);
```

For each Doctrine class with at least one `#[Versioned]` property, creates `<source>_version`:

| column | type | notes |
|--------|------|-------|
| `id` | integer | PK, autoincrement |
| `entity_id` | mirrors source id type | FK to `<source>.id`, ON DELETE CASCADE |
| `version` | integer | per-entity counter |
| `created_at` | datetimetz_immutable | snapshot timestamp |
| `<scalar>` columns | mirror source column types | one per versioned scalar/enum field |
| `<field>_id` columns | matches related entity id type | one per single-card relation |
| `<field>_version` columns | integer, nullable | one per single-card relation pointing at a `Versionable` target |

`(entity_id, version)` is uniquely indexed.

For each `#[Versioned]` collection, creates `<source>_version_<field>`:

| column | type | notes |
|--------|------|-------|
| `version_id` | integer | FK to `<source>_version.id`, ON DELETE CASCADE |
| `target_id` | matches related entity id type | id of the related element |
| `target_version` | integer, nullable | only when the related class is itself `Versionable` |

PK: `(version_id, target_id)`.

Scalar column options (`length`, `precision`, `scale`, `enumType`, `notnull`) are copied from the source mapping.
