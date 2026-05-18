# Usage patterns

## Wiring

```php
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\ToolEvents;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

$metadataFactory = new VersionableMetadataFactory();

$em->getEventManager()->addEventListener(
    [Events::onFlush, Events::postFlush],
    new VersionableListener($metadataFactory, $clock),
);

$em->getEventManager()->addEventListener(
    [ToolEvents::postGenerateSchema],
    new VersionableSchemaListener($metadataFactory),
);
```

## Marking fields

```php
#[ORM\Entity]
class Article
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    private int $id;

    #[Versioned]
    #[ORM\Column]
    private string $title;

    #[Versioned]
    #[ORM\Column(nullable: true)]
    private ?string $body = null;
}
```

Insert does not snapshot. The first time `title` or `body` changes and the unit-of-work is flushed, a row appears in `article_version` with `version = 1`.

## Service

Inject `VersionerInterface` (alias of `Versioner`):

```php
use SoureCode\Component\Versionable\VersionerInterface;

final class ArticleHistoryController
{
    public function __construct(
        private readonly VersionerInterface $versioner,
    ) {}
}
```

The bundle wires the service from `EntityManagerInterface` + `VersionableMetadataFactory`.

```php
$versioner->findHistory(Article::class, $id);          // list of rows, oldest first
$versioner->findByVersion(Article::class, $id, 2);     // single row or null
$versioner->findLatestVersion(Article::class, $id);    // single row or null
```

Rows come back as associative arrays — the version table is a flat snapshot, not a Doctrine entity.

## Reverting an entity

```php
$versioner->applyVersion($entity, 2);
$em->flush(); // writes a new version row capturing the revert
```

Mutates the live entity in place (class is inferred from the entity):

- Scalar fields are restored via the matching Doctrine type.
- Single-card associations are re-attached at their **current** state by looking up the stored FK (`$em->find(...)`).
- Collection associations are cleared and refilled from the snapshot's join rows.
- Historical state of related entities is **not** restored — the stored `target_version` is informational only; the live current target is what gets re-attached.

Throws `RuntimeException` when the version does not exist or the entity has no identifier.

## Composition with other behaviors

`Versionable` only tracks fields. Cross-cutting concerns like "who changed this" and "when was it deleted" belong to other behaviors — mark the relevant field `#[Versioned]` and the snapshot picks it up automatically.

### Blame: who made the change

Use [`Authorable`](../../Authorable/docs/index.md) for the `updatedBy` field, mark it tracked:

```php
use SoureCode\Component\Authorable\Attribute\UpdatedBy;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[Versioned]
#[UpdatedBy]
private ?User $updatedBy = null;
```

Each snapshot now records the author that produced it — no Versionable-side API, no extra column, no provider configuration.

### Soft-delete history

Mark the [Timestampable](../../Timestampable/docs/index.md) `#[DeletedAt]` and [Authorable](../../Authorable/docs/index.md) `#[DeletedBy]` markers tracked:

```php
use SoureCode\Component\Authorable\Attribute\DeletedBy;
use SoureCode\Component\Timestampable\Attribute\DeletedAt;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[Versioned]
#[DeletedAt]
private ?\DateTimeImmutable $deletedAt = null;

#[Versioned]
#[DeletedBy]
private ?User $deletedBy = null;
```

The soft-remove operation lives in the [`Removable`](../../Removable/docs/index.md) repository trait — it fills both markers in one call. Versionable then snapshots the transition like any other field: the `null → timestamp` change produces a snapshot row, and a subsequent `restore()` (`timestamp → null`) produces a symmetric one. Undelete history comes for free.

### General rule

A snapshot is a row of the fields you marked. Any behavior that exposes its state as a property can be tracked by adding `#[Versioned]`. No coupling between behaviors is required.
