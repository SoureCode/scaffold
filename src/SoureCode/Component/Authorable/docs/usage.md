# Usage patterns

## Author provider

Implement `AuthorProviderInterface` once at the integration layer.

```php
final class CurrentUserAuthorProvider implements AuthorProviderInterface
{
    public function __construct(private readonly Security $security) {}

    public function getCurrentAuthor(): ?object
    {
        return $this->security->getUser();
    }
}
```

When no author is available (e.g. CLI, background worker, anonymous request), return `null` — the listener becomes a no-op for that flush.

## Bare attributes

```php
#[ORM\Entity]
class Article
{
    #[CreatedBy]
    private ?User $createdBy = null;

    #[UpdatedBy]
    private ?User $updatedBy = null;
}
```

With the mapping listener wired, no `#[ORM\ManyToOne]` is required — it's auto-registered from the property's PHP type.

## Interface fallback

If an entity has **no** Authorable attributes but implements `AuthorableInterface`, the listener calls `setCreatedBy()` / `setUpdatedBy()` directly.

```php
final class LegacyEntity implements AuthorableInterface
{
    private ?object $createdBy = null;
    private ?object $updatedBy = null;
    // getters + setters
}
```

## Change tracking

### Fire when a relation pointer is cleared

```php
#[ChangedBy(field: 'parent', matchValue: true, value: null)]
private ?User $orphanedBy = null;
```

### Fire when status becomes a specific enum case

```php
#[ChangedBy(field: 'status', matchValue: true, value: Status::Published)]
private ?User $publishedBy = null;
```

### Fire on any change to one of several fields

```php
#[ChangedBy(field: ['title', 'body'])]
private ?User $contentEditedBy = null;
```

### Collection itself (add/remove)

```php
#[ChangedBy(field: 'tags')]
private ?User $lastTaggedBy = null;
```

## Soft-delete marker

```php
#[DeletedBy]
private ?User $deletedBy = null;
```

`#[DeletedBy]` is a pure marker. The flush listener never touches it — the caller assigns the value. Mapping listener registers a nullable `ManyToOne` to the property's PHP type.

Soft-remove orchestration (call `AuthorProviderInterface`, fill `deletedBy`, flush) lives in the [`Removable`](../../Removable/docs/index.md) component, which reads the marker via Authorable metadata.

The `AuthorableBundle` ships a [`DeletedByTrait`](../../../Bundle/AuthorableBundle/Doctrine/DeletedByTrait.php) typed against Symfony's `UserInterface` — same pattern as `CreatedByTrait` / `UpdatedByTrait`.

## Mapping override

Add `#[ORM\ManyToOne]` manually only when you need to override defaults (custom join column name, fetch mode, target entity disambiguation, etc.):

```php
#[CreatedBy]
#[ORM\ManyToOne(targetEntity: User::class, fetch: 'EAGER')]
#[ORM\JoinColumn(name: 'creator_id', nullable: false, onDelete: 'RESTRICT')]
private ?User $createdBy = null;
```
