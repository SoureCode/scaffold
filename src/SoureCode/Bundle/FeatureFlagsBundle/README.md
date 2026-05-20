# sourecode/feature-flags-bundle

Symfony wiring for [`sourecode/feature-flags`](../../Component/FeatureFlags/README.md). Registers the Doctrine-backed manager, wires the `feature_enabled` Twig function, and configures the Doctrine mapping for the chosen `FeatureFlag` entity class.

## Install

Part of the `scaffold` monorepo. Symfony Flex registers the bundle automatically.

## Configuration

```yaml
feature_flags:
    entity_class: SoureCode\Component\FeatureFlags\Model\FeatureFlag
    table_name:   feature_flags
```

| key | default | meaning |
|-----|---------|---------|
| `entity_class` | `FeatureFlag` | FQCN implementing `FeatureFlagInterface`. Use your own when you need extra columns. |
| `table_name` | `feature_flags` | Doctrine table for the configured class. |

Invalid `entity_class` (does not implement `FeatureFlagInterface`) raises `InvalidArgumentException` at container compile time.

## Minimal example

```php
use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;

final class CheckoutController
{
    public function __construct(private readonly FeatureFlagsManagerInterface $flags) {}

    public function __invoke(): Response
    {
        if ($this->flags->isEnabled('checkout.v2')) {
            // …
        }
    }
}
```

```twig
{% if feature_enabled('checkout.v2') %}
    {{ include('checkout/v2.html.twig') }}
{% else %}
    {{ include('checkout/v1.html.twig') }}
{% endif %}
```

## Public surface

| Service id | Role |
|------------|------|
| `SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface` | The flag store (alias to `DoctrineFeatureFlagsManager`). |
| `SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactoryInterface` | Builds new `FeatureFlagInterface` instances of the configured class. |
| `feature_enabled(name)` | Twig function, equivalent to `manager.isEnabled(name)`. |

## Doctrine mapping

The bundle registers a mapping driver scoped to the namespace `SoureCode\Component\FeatureFlags\Model`. The chosen `entity_class` is mapped automatically — no XML / YAML / attribute config on the entity, even when it's your own subclass declared elsewhere.

## Behavior and limits

See the [component README](../../Component/FeatureFlags/README.md).
