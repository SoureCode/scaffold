# Usage patterns

## Service

```php
use SoureCode\Component\Lifecycle\RemoverInterface;

final class ArticleController
{
    public function __construct(private readonly RemoverInterface $remover) {}

    public function delete(Article $article): Response
    {
        $this->remover->remove($article);
        // …
    }
}
```

## Soft remove

```php
$remover->remove($article);                  // fill deletedAt + deletedBy, flush
$remover->remove($article, flush: false);    // mutate only
```

## Hard remove

```php
$remover->remove($article, soft: false);     // EntityManager::remove + flush
```

## Restore

```php
$remover->restore($article);                 // clear both markers, flush
$remover->restore($article, flush: false);   // mutate only
```

## Filtering out soft-deleted rows

`Removable` writes the markers; reads stay your responsibility. Common patterns:

- A repository method that always adds `WHERE deletedAt IS NULL`.
- A Doctrine SQL filter enabled on the EntityManager.
- An entity listener that scopes the queries you care about.
