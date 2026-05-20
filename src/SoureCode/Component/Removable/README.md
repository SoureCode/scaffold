# sourecode/removable

Soft-remove orchestration for Doctrine entities. Reads `#[DeletedAt]` ([Timestampable](../Timestampable/README.md)) and `#[DeletedBy]` ([Authorable](../Authorable/README.md)), fills them in one call, and pairs that with a symmetric `restore()`. No attribute of its own.

## When to use

You want one service that turns a "delete this" intent into the right marker writes — and the same service to undo them — without rewriting the orchestration in every controller.

## When not to use

You want hard deletes. Call `EntityManager::remove()` yourself, or call `Remover::remove($entity, soft: false)`.

## Install

Part of the `scaffold` monorepo.

## Minimal example

```php
use SoureCode\Component\Authorable\Attribute\DeletedBy;
use SoureCode\Component\Timestampable\Attribute\DeletedAt;

#[ORM\Entity]
class Article
{
    #[DeletedAt]
    private ?\DateTimeImmutable $deletedAt = null;

    #[DeletedBy]
    private ?User $deletedBy = null;
}
```

```php
$remover->remove($article);   // fills deletedAt + deletedBy
$remover->restore($article);  // clears both
```

`#[DeletedBy]` is optional; without it, only `#[DeletedAt]` is filled.

## Public surface

```php
interface RemoverInterface
{
    public function remove(object $entity, bool $soft = true, bool $flush = true): void;
    public function restore(object $entity, bool $flush = true): void;
}
```

## Behavior

| Call | Effect |
|------|--------|
| `remove($entity)` | Fill `#[DeletedAt]` (and `#[DeletedBy]` if present), flush. |
| `remove($entity, soft: false)` | Hard delete via `EntityManager::remove()`. |
| `remove($entity, flush: false)` | Mutate but don't flush. |
| `restore($entity)` | Clear `#[DeletedAt]` and `#[DeletedBy]`, flush. |

`#[DeletedBy]` is filled only when an `AuthorProviderInterface` is wired and returns a non-null actor; otherwise it stays untouched.

## Composition

- [`Timestampable`](../Timestampable/README.md) — owns `#[DeletedAt]`.
- [`Authorable`](../Authorable/README.md) — owns `#[DeletedBy]`.
- [`Versionable`](../Versionable/README.md) — mark both markers `#[Versioned]` to get a snapshot for delete and restore.

## Limits

- The entity must declare `#[DeletedAt]`. Calling `remove()` on an entity without it raises `LogicException`.
- Filtering deleted entities out of queries is the caller's job (e.g. a Doctrine filter, a repository scope).

## Stability

`RemoverInterface`, the `remove`/`restore` signatures, and the soft/hard semantics are stable.
