# scaffold

SoureCode monorepo. Symfony-style component + bundle layout, single root install.

## Layout

```
src/SoureCode/Component/<Name>/        # framework-agnostic component
src/SoureCode/Bundle/<Name>Bundle/     # Symfony bundle integration
```

Each package has its own `composer.json` (name, autoload, deps). The root `composer.json` is a generated union — never hand-edit it.

## Workflow

Add a dependency to a package:

```bash
composer require --no-install --working-dir=src/SoureCode/Component/<Name> <vendor>/<package>
```

Regenerate the root manifest and install:

```bash
bin/merge
composer install
```

Run the test suite:

```bash
vendor/bin/phpunit
```

## Packages

| Package | Path |
|---------|------|
| `sourecode/doctrine-extensions` | `src/SoureCode/Component/DoctrineExtensions/` |
| `sourecode/timestampable` | `src/SoureCode/Component/Timestampable/` |
| `sourecode/timestampable-bundle` | `src/SoureCode/Bundle/TimestampableBundle/` |
| `sourecode/authorable` | `src/SoureCode/Component/Authorable/` |
| `sourecode/authorable-bundle` | `src/SoureCode/Bundle/AuthorableBundle/` |
| `sourecode/versionable` | `src/SoureCode/Component/Versionable/` |
| `sourecode/versionable-bundle` | `src/SoureCode/Bundle/VersionableBundle/` |

## Requirements

- PHP `>=8.5`
- Symfony components `^8.0`
