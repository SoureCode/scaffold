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
$versioner->findHistory(Article::class, $id);          // list<ArticleHistory>, oldest first
$versioner->findByVersion(Article::class, $id, 2);     // ?ArticleHistory
$versioner->findLatestVersion(Article::class, $id);    // ?ArticleHistory
```

Each returned object is a runtime-generated `*History` class (under `SoureCode\Versionable\Generated\…`), not the live entity. It exposes one getter per versioned scalar / embedded field, the `id`, the snapshot `version`, plus relation getters that resolve to the partner's `*History` at the recorded pin (transitive walk).

```php
$articleHistory = $versioner->findByVersion(Article::class, $id, 2);

$articleHistory->getId();                       // identifier
$articleHistory->getVersion();                  // 2
$articleHistory->getTitle();                    // scalar at v=2
$articleHistory->getAuthor()?->getName();       // AuthorHistory at the pinned version
$articleHistory->getTags();                     // list<TagHistory>
```

Cycles short-circuit to `null` on the second visit of the same `(class, id, version)` tuple within a single hydration pass.

To get a typed-safe `assertInstanceOf` target, ask the Versioner for the generated FQCN:

```php
Versioner::historyClassFor(Article::class);  // "SoureCode\\Versionable\\Generated\\…\\ArticleHistory"
```

## Walking history from a live entity

Loaded entities are runtime-generated proxy subclasses with `get<Field>History()` per owning versioned relation:

```php
$post = $em->find(Post::class, $id);

$post->getAuthor();           // live, managed Author
$post->getAuthorHistory();    // AuthorHistory pinned at post.author_version

$post->getAuthorHistory()
     ->getCompany()           // CompanyHistory at recorded company_version
     ?->getName();
```

The pin lives in the live `<entity>.<field>_version` column; it captures the related entity's version at the moment the owner was last flushed and stays frozen until the next flush of the owner. Standalone bumps on the related entity do not change the pin.

Promotion back to the live, mutable entity is the standard Doctrine call:

```php
$em->find(Author::class, $post->getAuthorHistory()->getId());
```

## Reverting an entity

```php
$versioner->applyVersion($article, 2);
$em->flush(); // writes a new snapshot capturing the revert
```

- Versioned scalar fields are restored to the values stored in the snapshot.
- Single-card associations are re-attached at their *current* state by looking up the stored FK; pass `cascade: true` to also revert each related versioned entity to the version captured at the parent snapshot.
- Collection associations are cleared and refilled from the snapshot's join rows.

`RuntimeException` if the version does not exist or the entity has no identifier.

## Suppressing relationship propagation

Default behavior: a relation change ripples — both ends bump and get their own snapshot. To opt out of the ripple on a specific flush:

```php
$post->setAuthor($newAuthor);
$versioner->bumpRelations(false);   // one-shot for the next flush
$em->flush();                        // → post bumps, author does not
```

`applyVersion()` carries the same flag inline:

```php
$versioner->applyVersion($post, 3, bumpRelations: false);
$em->flush();
```

To make a whole class default to "no ripple" (then optionally override per flush), set the attribute argument:

```php
#[Versioned(bumpRelations: false)]
class AuditEntry { … }
```

The runtime flag, when set, wins for the duration of the flush; otherwise each entity follows its own class-level default.

## Composition

`Versionable` tracks the whole entity. Cross-cutting state (who, when) managed by other behaviors is captured automatically — no per-field marking.

### Author per snapshot

```php
use SoureCode\Component\Lifecycle\Attribute\UpdatedBy;

#[UpdatedBy]
private ?User $updatedBy = null;
```

When the entity is `#[Versioned]`, every snapshot records who produced it.

### Soft-delete history

```php
use SoureCode\Component\Lifecycle\Attribute\DeletedBy;
use SoureCode\Component\Lifecycle\Attribute\DeletedAt;

#[DeletedAt]
private ?\DateTimeImmutable $deletedAt = null;

#[DeletedBy]
private ?User $deletedBy = null;
```

The soft-delete transition (`null → timestamp`) and `restore()` (`timestamp → null`) each appear as snapshots. Undelete history comes for free.

### General rule

Mark the entity `#[Versioned]`; every mapped field — including those managed by other behaviors — becomes historical. No per-field coupling.
