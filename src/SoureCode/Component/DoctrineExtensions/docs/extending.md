# Build your own behavior

Recipe for a new `*-able` package on top of `sourecode/doctrine-extensions`.

## Pick the lifecycle

| Binding contract | Fires |
|------------------|-------|
| `PersistBindingInterface` | `prePersist`, only when the property is `null` |
| `UpdateBindingInterface` | `prePersist` (unless `isNullable()`) + every `onFlush` for scheduled updates |
| `ChangeBindingInterface` | `onFlush` when one of the watched fields appears in the changeset |

A class can implement more than one.

## 1. Attribute

```php
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class TouchedBy
{
    public function __construct(public readonly bool $nullable = true) {}
}
```

## 2. Binding

```php
final class TouchedByBinding implements UpdateBindingInterface
{
    public function __construct(
        public readonly \ReflectionProperty $property,
        public readonly bool $nullable,
    ) {}

    public function getProperty(): \ReflectionProperty { return $this->property; }
    public function isNullable(): bool { return $this->nullable; }
}
```

## 3. Metadata + factory

```php
final class TouchableMetadata implements BehaviorMetadataInterface
{
    /** @param list<TouchedByBinding> $updateBindings */
    public function __construct(public readonly array $updateBindings) {}

    public function getPersistBindings(): array { return []; }
    public function getUpdateBindings(): array  { return $this->updateBindings; }
    public function getChangeBindings(): array  { return []; }
    public function isEmpty(): bool             { return $this->updateBindings === []; }
}
```

`BehaviorMetadataFactoryInterface::getMetadataFor($class)` reflects the class and produces a `TouchableMetadata` — cache it per class.

## 4. Listener

```php
final class TouchableListener extends AbstractFlushListener
{
    public function __construct(
        private readonly SomeProvider $provider,
        TouchableMetadataFactory $factory,
        ChangeSetMatcher $matcher,
    ) {
        parent::__construct($factory, $matcher);
    }

    protected function shouldRun(): bool
    {
        return $this->provider->getValue() !== null;
    }

    protected function resolveValue(\ReflectionProperty $property): mixed
    {
        return $this->provider->getValue();
    }

    protected function handlePersistInterfaceFallback(object $entity): void {}

    protected function handleUpdateInterfaceFallback(object $entity, EntityManagerInterface $em, UnitOfWork $uow): void {}
}
```

## 5. Wire

```php
$em->getEventManager()->addEventListener(
    [Events::prePersist, Events::onFlush],
    new TouchableListener($provider, $factory, new ChangeSetMatcher()),
);
```

## What you inherit

- Dotted-path traversal (`address.city`, `owner.department.code`).
- Collection traversal (`tags.title`, `tags`).
- Cycle protection via `SplObjectStorage`.
- Propagation across scheduled insert / update / delete on related entities.
- `recomputeSingleEntityChangeSet` after each touch.
- Backed-enum value normalization for value matchers.

## Subclass hooks

| Hook | Purpose |
|------|---------|
| `shouldRun(): bool` | Gate the whole listener (e.g. no current author → skip). |
| `resolveValue(\ReflectionProperty): mixed` | Value written into a touched property. |
| `handlePersistInterfaceFallback(object)` | Called when the entity has no behavior attributes but implements an opt-in interface. |
| `handleUpdateInterfaceFallback(object, $em, $uow)` | Same, for `onFlush`. |
