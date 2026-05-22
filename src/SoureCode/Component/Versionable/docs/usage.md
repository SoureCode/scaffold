# Usage patterns

## Reading history

Inject `VersionerInterface` (alias of `Versioner`):

```php
use SoureCode\Component\Versionable\VersionerInterface;

final class ArticleHistoryController
{
    public function __construct(private readonly VersionerInterface $versioner) {}
}
```

```php
$versioner->findHistory(Article::class, $id);          // list of snapshots, oldest first
$versioner->findByVersion(Article::class, $id, 2);     // one snapshot or null
$versioner->findLatestVersion(Article::class, $id);    // one snapshot or null
```

Each row is the hydrated entity at that point in time, scoped to the versioned fields. Identifiers and unversioned fields are not part of the snapshot.

## Reverting an entity

```php
$versioner->applyVersion($article, 2);
$em->flush(); // writes a new snapshot capturing the revert
```

- Versioned scalar fields are restored to the values stored in the snapshot.
- Single-card associations are re-attached at their *current* state by looking up the stored FK.
- Collection associations are cleared and refilled from the snapshot's join rows.
- Historical state of related entities is **not** restored — `target_version` is informational.

`RuntimeException` if the version does not exist or the entity has no identifier.

## Composition

`Versionable` only tracks fields. Cross-cutting state (who, when) lives in the dedicated behaviors — mark the relevant field `#[Versioned]` and the snapshot picks it up.

### Author per snapshot

```php
use SoureCode\Component\Lifecycle\Attribute\UpdatedBy;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[Versioned]
#[UpdatedBy]
private ?User $updatedBy = null;
```

Every snapshot records who produced it.

### Soft-delete history

```php
use SoureCode\Component\Lifecycle\Attribute\DeletedBy;
use SoureCode\Component\Lifecycle\Attribute\DeletedAt;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[Versioned]
#[DeletedAt]
private ?\DateTimeImmutable $deletedAt = null;

#[Versioned]
#[DeletedBy]
private ?User $deletedBy = null;
```

The soft-delete transition (`null → timestamp`) and `restore()` (`timestamp → null`) each appear as snapshots. Undelete history comes for free.

### General rule

Any property tracked by another behavior can be made historical by adding `#[Versioned]`. No coupling required.
