# sourecode/doctrine-extensions-bundle

Registers the shared services from [`sourecode/doctrine-extensions`](../../Component/DoctrineExtensions/README.md). Pulled in transitively by every other `*Bundle` in this monorepo.

## When to use

Required by `TimestampableBundle`, `AuthorableBundle`, `VersionableBundle`. Nothing to configure manually unless you depend on `ChangeSetMatcher` directly in your own services.

## Install

Part of the `scaffold` monorepo. Symfony Flex registers the bundle automatically.

## Public surface

| Service id | Class |
|------------|-------|
| `SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher` | `ChangeSetMatcher` |
