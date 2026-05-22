# Composing the SoureCode toolkit

The capabilities are designed to layer onto a single entity without
fighting each other. This guide walks through the typical "Article with
audit trail + versioning + soft delete" composition.

## The canonical entity

```php
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\AuthorableBundle\Doctrine\{CreatedByTrait, UpdatedByTrait, DeletedByTrait};
use SoureCode\Bundle\TimestampableBundle\Doctrine\{CreatedAtTrait, UpdatedAtTrait, DeletedAtTrait};
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
class Article
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use DeletedAtTrait;
    use CreatedByTrait;
    use UpdatedByTrait;
    use DeletedByTrait;

    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column, Versioned]
    public string $title = '';
}
```

That single class is now:

- **Timestamped** — `CreatedAt` set on insert, `UpdatedAt` advanced on every
  update unless you set one explicitly in the same transaction.
- **Audited** — `CreatedBy`/`UpdatedBy` populated by whatever
  `AuthorProviderInterface` you wired (`SecurityAuthorProvider` by default).
- **Soft-deletable** — `Remover::remove()` stamps `DeletedAt` + `DeletedBy`;
  `Remover::restore()` clears both. With `RemovableBundle.soft_delete_filter.enabled`
  set, `findAll()` and friends automatically exclude soft-deleted rows.
- **Versioned** — every flush where `title` changed creates a row in
  `article_version` capturing the new value, the current author and a
  monotonic version number.

## Recommended bundle config (Symfony Flex `config/packages/*.yaml`)

```yaml
# config/packages/sourecode.yaml
authorable: ~

timestampable: ~

removable:
  soft_delete_filter:
    enabled: true

versionable: ~

traceable:
  http:
    accept_incoming: trusted     # honour X-Request-Id only behind a proxy

recent_authentication:
  ttl: 900
  login_route: sourecode_reauth   # use the shipped controller, or your own

feature_flags:
  env_override:
    enabled: true
```

## Ordering

Listeners run in this implicit order during a single `flush()`:

1. `TimestampableListener` stamps `CreatedAt` / `UpdatedAt` /
   `ChangedAt`.
2. `AuthorableListener` stamps `CreatedBy` / `UpdatedBy` / `ChangedBy`.
3. `VersionableListener.onFlush` collects entities to snapshot.
4. `VersionableListener.postFlush` writes the snapshot rows *after* the
   primary entity changes are committed — so snapshots capture the
   stamped values, never half-stamped intermediate state.

You do not need to declare priorities explicitly; the implementation
already chooses the right phase for each behavior. Custom listeners that
need to slot in between these phases can implement
`PrioritizedFlushListenerInterface`.

## Per-capability cheat sheet

| Capability      | Hook    | What to do                                              |
| --------------- | ------- | ------------------------------------------------------- |
| Timestampable   | trait   | `use CreatedAtTrait;` etc.                              |
| Authorable      | trait   | `use CreatedByTrait;` etc.                              |
| Removable       | service | `$remover->remove($entity)` / `restore($entity)`        |
| Versionable     | attr.   | `#[Versioned]` on each property to snapshot             |
| Settings        | service | `$settings->get('site.title')`                          |
| FeatureFlags    | service | `$flags->isEnabledFor('checkout.v2', ['user_id' => ...])` |
| RecentAuth      | voter   | `IS_AUTHENTICATED_RECENTLY` (optional int = per-attr ttl) |
| Traceable       | passive | request / console / messenger auto-stamps trace id     |

## Composition tests

See `src/SoureCode/Bundle/RemovableBundle/Tests/CompositionTest.php` for a
working example that boots all bundles in one Kernel and runs every
behavior in sequence on a single entity.
