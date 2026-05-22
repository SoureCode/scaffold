# sourecode/settings

Global key/value settings store. One service (`SettingsManagerInterface`) for app-wide configuration that lives in the database, not in YAML — branding colors, opening hours, support contact, anything an operator should be able to change without a deploy.

## When to use

You want a small, typed key → JSON value store that any service can read or write at runtime.

## When not to use

- You need on/off toggles for rolling out features — use [`sourecode/feature-flags`](../FeatureFlags/README.md). It's a dedicated boolean store with its own manager, name validation, and Twig helper.
- You need per-tenant, per-user, or per-environment scoped settings. This component is single-scope (one row per key, global). Wrap your own scoping on top, or pick a different store.

## Install

Part of the `scaffold` monorepo. The [`settings-bundle`](../../Bundle/SettingsBundle/README.md) wires the Doctrine-backed manager.

## Minimal example

```php
use SoureCode\Component\Settings\Manager\SettingsManagerInterface;

final class HomepageController
{
    public function __construct(private readonly SettingsManagerInterface $settings) {}

    public function __invoke(): Response
    {
        $primary      = $this->settings->get('brand.color.primary', '#000');
        $supportEmail = $this->settings->get('contact.support', 'support@example.com');
        // …
    }
}
```

## Public surface

```php
interface SettingsManagerInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function has(string $key): bool;
    public function set(string $key, mixed $value): void;
    public function remove(string $key): void;

    /** @return Collection<string, SettingInterface> */
    public function all(): Collection;
}
```

| Implementation | Use for |
|----------------|---------|
| `DoctrineSettingsManager` | Production. Reads / writes one Doctrine entity per key. |
| `InMemorySettingsManager` | Tests. No persistence. Same contract. |

Values round-trip as `mixed` — Doctrine stores them in a JSON column, so anything `json_encode`-able works (scalars, arrays, nested arrays).

## Key syntax

Keys match `/^[a-z0-9][a-z0-9._-]*$/`. Dot, dash, underscore allowed; must start with a letter or digit. Invalid keys raise `InvalidArgumentException` at every entry point.

## Custom entity class

`SettingInterface` is the contract; the shipped `Setting` is the default Doctrine-mapped model. Provide your own class when you need extra columns, audit attributes, or a different table layout:

```php
use SoureCode\Component\Settings\Model\SettingInterface;

#[ORM\Entity]
class CustomSetting implements SettingInterface
{
    // implement getKey/setKey/getValue/setValue
}
```

Pass it to the [`SettingMappingDriver`](Doctrine/SettingMappingDriver.php) and the manager:

```php
$driver  = new SettingMappingDriver(CustomSetting::class, 'custom_settings');
$manager = new DoctrineSettingsManager($em, CustomSetting::class);
```

The [bundle](../../Bundle/SettingsBundle/README.md) does this automatically via `settings.entity_class` and `settings.table_name`.

## Composition

- [`Authorable`](../Authorable/README.md) / [`Timestampable`](../Timestampable/README.md) — declare a custom entity class and add `#[CreatedBy]`, `#[UpdatedAt]`, etc.
- [`Versionable`](../Versionable/README.md) — mark the `value` property `#[Versioned]` to keep history of every change.

## Limits

- Single, global scope. No tenant / user / env partitioning built in.
- `value` is stored as JSON. Resources, closures, non-`json_encode`-able objects don't round-trip.
- `set()` and `remove()` flush eagerly. Calling them inside another transaction commits early.
- Writes are not race-safe. Two processes calling `set()` for the same brand-new key at the same time will see one of them raise `Doctrine\DBAL\Exception\UniqueConstraintViolationException`, and Doctrine ORM 3.x will close the EntityManager afterwards. Serialize writes upstream (queue, advisory lock, retry wrapper with a fresh EM) if concurrent writers are expected.

## Stability

`SettingsManagerInterface`, `SettingInterface`, the key syntax, and the two manager implementations are stable.
