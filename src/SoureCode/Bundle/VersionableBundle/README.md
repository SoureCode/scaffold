# sourecode/versionable-bundle

Symfony bundle wiring for [`sourecode/versionable`](../../Component/Versionable/README.md).

## Install

Part of the `scaffold` monorepo — always installed with the rest.

Symfony Flex registers the bundle (and its prerequisites `DoctrineBundle` + `DoctrineExtensionsBundle`) automatically. No configuration block.

## Services registered

| Service id | Tagged event |
|-----------|--------------|
| `VersionableMetadataFactory` | — |
| `ClockInterface` (`Symfony\Component\Clock\Clock`) | — |
| `VersionableListener` | `doctrine.event_listener` (`onFlush`) |
| `VersionableSchemaListener` | `doctrine.event_listener` (`postGenerateSchema`) |

## Usage

Annotate properties with `#[Versioned]` — see the [component README](../../Component/Versionable/README.md).

### Bundled `AbstractVersionableRepository`

For repositories that should expose version queries on the host entity, extend `AbstractVersionableRepository`:

```php
use SoureCode\Bundle\VersionableBundle\Repository\AbstractVersionableRepository;

/**
 * @extends AbstractVersionableRepository<Article>
 */
final class ArticleRepository extends AbstractVersionableRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }
}
```

`$em->getRepository(Article::class)->findHistory($id)` works.

If your repository already extends a different base, mix in `SoureCode\Component\Versionable\Repository\VersionableRepositoryTrait` instead.
