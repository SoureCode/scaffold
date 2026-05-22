# sourecode/settings-bundle

Symfony wiring for [`sourecode/settings`](../../Component/Settings/README.md). Registers the Doctrine-backed manager, wires the `setting` Twig function, and configures the Doctrine mapping for the chosen `Setting` entity class.

## Install

Part of the `scaffold` monorepo. Symfony Flex registers the bundle automatically.

## Configuration

```yaml
settings:
    entity_class: SoureCode\Component\Settings\Model\Setting
    table_name:   settings
```

| key | default | meaning |
|-----|---------|---------|
| `entity_class` | `Setting` | FQCN implementing `SettingInterface`. Use your own when you need extra columns or behavior attributes. |
| `table_name` | `settings` | Doctrine table for the configured class. |

Invalid `entity_class` (does not implement `SettingInterface`) raises `InvalidArgumentException` at container compile time.

## Minimal example

```php
use SoureCode\Component\Settings\Manager\SettingsManagerInterface;

final class BrandingService
{
    public function __construct(private readonly SettingsManagerInterface $settings) {}

    public function primaryColor(): string
    {
        return (string) $this->settings->get('brand.color.primary', '#000');
    }
}
```

```twig
<a href="mailto:{{ setting('contact.support', 'support@example.com') }}">Contact support</a>
```

> Looking for boolean rollout toggles? Use [`sourecode/feature-flags-bundle`](../FeatureFlagsBundle/README.md) instead.

## Public surface

| Service id | Role |
|------------|------|
| `SoureCode\Component\Settings\Manager\SettingsManagerInterface` | The settings store (alias to `DoctrineSettingsManager`). |
| `setting(key, default = null)` | Twig function, equivalent to `manager.get(key, default)`. |

## Doctrine mapping

The bundle registers a mapping driver scoped to the namespace `SoureCode\Component\Settings\Model`. The chosen `entity_class` is mapped automatically — no XML / YAML / attribute config needed on the entity, even when it's your own subclass declared elsewhere.

## Behavior and limits

See the [component README](../../Component/Settings/README.md).
