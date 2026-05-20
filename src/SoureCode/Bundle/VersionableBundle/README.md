# sourecode/versionable-bundle

Symfony wiring for [`sourecode/versionable`](../../Component/Versionable/README.md). Installing the bundle is enough — entities annotated with `#[Versioned]` start producing snapshot rows on flush.

## Install

Part of the `scaffold` monorepo. Symfony Flex registers the bundle automatically. No configuration block.

## Public surface

| Service id | Role |
|------------|------|
| `SoureCode\Component\Versionable\VersionerInterface` | Read snapshot rows and revert entities — `findHistory()`, `findByVersion()`, `findLatestVersion()`, `applyVersion()`. |
| `Psr\Clock\ClockInterface` (alias of `Symfony\Component\Clock\Clock`) | Used by the listener; inject elsewhere if needed. |

## Behavior

See the [component README](../../Component/Versionable/README.md).
