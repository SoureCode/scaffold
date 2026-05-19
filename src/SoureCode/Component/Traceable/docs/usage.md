# Usage patterns

## Reading the current trace id

```php
use SoureCode\Component\Traceable\TraceContextHolder;

final class SomeLogger
{
    public function __construct(
        private readonly TraceContextHolder $traceContextHolder,
    ) {}

    public function log(string $message): void
    {
        $context = $this->traceContextHolder->getCurrent();
        $traceId = $context?->getId();
        // ...
    }
}
```

`getCurrent()` returns `null` when no trace is active.

## Setting a trace id

```php
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Uid\Ulid;

$factory = new TraceContextFactory();
$holder = new TraceContextHolder();

$holder->setCurrent($factory->create());                 // generates a fresh Ulid
$holder->setCurrent($factory->create(new Ulid('...'))); // explicit id
$holder->setCurrent(null);                                // clear
```

## Sources

The component does not bind to a runtime. Concrete sources (HTTP request listener, console event listener, messenger middleware) are wired by the bundle. See [`sourecode/traceable-bundle`](../../../Bundle/TraceableBundle/README.md).

Manual usage works too — application code can call `setCurrent()` directly from any handler that has a meaningful correlation id.
