# Usage patterns

## Author provider

Implement `AuthorProviderInterface` once.

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

The [bundle](../../../Bundle/AuthorableBundle/README.md) ships a default that does exactly this when `symfony/security-bundle` is available.

Return `null` whenever no author makes sense (anonymous, CLI, worker) — the property is left untouched.

## Interface fallback

When an entity declares no Authorable attributes but implements `AuthorableInterface`, the listener calls `setCreatedBy()` / `setUpdatedBy()` directly. Useful for legacy entities you can't decorate.

```php
final class LegacyArticle implements AuthorableInterface
{
    private ?object $createdBy = null;
    private ?object $updatedBy = null;
    // getters + setters
}
```

Attributes and interface fallback don't mix: any Authorable attribute disables the interface path.

## `#[ChangedBy]` recipes

### Fire when status becomes a specific enum case

```php
#[ChangedBy(field: 'status', matchValue: true, value: Status::Published)]
private ?User $publishedBy = null;
```

### Fire when a relation pointer is cleared

```php
#[ChangedBy(field: 'parent', matchValue: true, value: null)]
private ?User $orphanedBy = null;
```

### Fire on any change across several fields

```php
#[ChangedBy(field: ['title', 'body'])]
private ?User $contentEditedBy = null;
```

### Collection itself

```php
#[ChangedBy(field: 'tags')]
private ?User $lastTaggedBy = null;
```

## Soft-delete marker

```php
#[DeletedBy]
private ?User $deletedBy = null;
```

The flush listener never touches it. Use [`Removable`](../../Removable/README.md) for the soft-delete + restore orchestration.

The [`AuthorableBundle`](../../../Bundle/AuthorableBundle/README.md) ships a `DeletedByTrait` already wired to `#[DeletedBy]`.

## Overriding the auto-mapping

```php
#[CreatedBy]
#[ORM\ManyToOne(targetEntity: User::class, fetch: 'EAGER')]
#[ORM\JoinColumn(name: 'creator_id', nullable: false, onDelete: 'RESTRICT')]
private ?User $createdBy = null;
```
