# sourecode/feature-flags

Global on/off feature flags. One service (`FeatureFlagsManagerInterface`) for enabling and disabling features at runtime without a redeploy.

## When to use

You want simple boolean rollout switches an operator can toggle live.

## When not to use

- You need free-form configuration values (colors, emails, opening hours) — use [`sourecode/settings`](../Settings/README.md).
- You need percentage rollouts, per-user targeting, or A/B variants. This component is a single boolean per flag with no scoping.

## Install

Part of the `scaffold` monorepo. The [`feature-flags-bundle`](../../Bundle/FeatureFlagsBundle/README.md) wires the Doctrine-backed manager.

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

## Public surface

```php
interface FeatureFlagsManagerInterface
{
    public function isEnabled(string $name): bool;
    public function has(string $name): bool;
    public function enable(string $name): void;
    public function disable(string $name): void;
    public function remove(string $name): void;

    /** @return Collection<string, FeatureFlagInterface> */
    public function all(): Collection;
}
```

| Implementation | Use for |
|----------------|---------|
| `DoctrineFeatureFlagsManager` | Production. One Doctrine row per flag. |
| `InMemoryFeatureFlagsManager` | Tests. No persistence. Same contract. |

Unknown flag → `isEnabled()` returns `false`. There is no default-on mode.

## Name syntax

Flag names match `/^[a-z0-9][a-z0-9._-]*$/`. Dot, dash, underscore allowed; must start with a letter or digit. Invalid names raise `InvalidArgumentException` at every entry point.

## Custom entity class

`FeatureFlagInterface` is the contract; the shipped `FeatureFlag` is the default Doctrine-mapped model. Provide your own class when you need extra columns (description, owner, last-toggled-at):

```php
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

#[ORM\Entity]
class CustomFeatureFlag implements FeatureFlagInterface
{
    // implement getName/setName/isEnabled/setEnabled
}
```

Pass it to the [`FeatureFlagMappingDriver`](Doctrine/FeatureFlagMappingDriver.php) and the manager:

```php
$driver  = new FeatureFlagMappingDriver(CustomFeatureFlag::class, 'custom_feature_flags');
$factory = new FeatureFlagFactory(CustomFeatureFlag::class);
$manager = new DoctrineFeatureFlagsManager($em, CustomFeatureFlag::class, $factory);
```

The [bundle](../../Bundle/FeatureFlagsBundle/README.md) does this via `feature_flags.entity_class` and `feature_flags.table_name`.

## Composition

- [`Authorable`](../Authorable/README.md) / [`Timestampable`](../Timestampable/README.md) — on a custom entity class, add `#[UpdatedBy]` / `#[UpdatedAt]` to capture who flipped a flag and when.
- [`Versionable`](../Versionable/README.md) — mark `enabled` `#[Versioned]` for a full history of every flip.

## Limits

- Global scope. No per-tenant, per-user, or per-environment partitioning.
- One boolean per name. Use [`Settings`](../Settings/README.md) for richer values.
- `enable()`, `disable()`, `remove()` flush eagerly.

## Stability

`FeatureFlagsManagerInterface`, `FeatureFlagInterface`, the name syntax, and the two manager implementations are stable.
