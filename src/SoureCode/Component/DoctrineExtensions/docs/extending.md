# Extending: building your own behavior

Recipe for a new `*-able` package on top of `doctrine-extensions`.

## 1. Attributes

```php
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class TouchedBy
{
    public function __construct(public readonly bool $nullable = true) {}
}
```

## 2. Bindings

Implement the contract that matches the lifecycle you need:

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

Implement `BehaviorMetadataInterface` and `BehaviorMetadataFactoryInterface`.

```php
final class TouchableMetadata implements BehaviorMetadataInterface
{
    public function __construct(
        public readonly array $updatedBindings,
    ) {}

    public function getPersistBindings(): array { return []; }
    public function getUpdateBindings(): array { return $this->updatedBindings; }
    public function getChangeBindings(): array { return []; }
    public function isEmpty(): bool { return $this->updatedBindings === []; }
}
```

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

    protected function handlePersistInterfaceFallback(object $entity): void { }
    protected function handleUpdateInterfaceFallback(object $entity, $em, $uow): void { }
}
```

## 5. Wire up

```php
$em->getEventManager()->addEventListener([Events::prePersist, Events::onFlush], $listener);
```

That's it — you get full path traversal, cycle protection, collection support, insert/update/delete propagation for free.
