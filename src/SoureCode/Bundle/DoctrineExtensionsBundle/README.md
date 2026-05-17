# sourecode/doctrine-extensions-bundle

Symfony bundle registering the shared services from [`sourecode/doctrine-extensions`](../../Component/DoctrineExtensions/README.md). Required by `sourecode/timestampable-bundle` and `sourecode/authorable-bundle`.

## Install

Part of the `scaffold` monorepo — always installed with the rest.

Symfony Flex registers the bundle automatically. No configuration block. No application-facing services to wire — downstream bundles consume `ChangeSetMatcher` directly.

## Services registered

| Service id | Class |
|-----------|-------|
| `SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher` | `ChangeSetMatcher` |
