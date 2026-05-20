# Usage patterns

## Reading the current trace id

```php
use SoureCode\Component\Traceable\TraceContextHolder;

final class SomeService
{
    public function __construct(private readonly TraceContextHolder $traceContextHolder) {}

    public function logSomething(string $message): void
    {
        $traceId = $this->traceContextHolder->getCurrent()?->getId();
        // …
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
$holder  = new TraceContextHolder();

$holder->setCurrent($factory->create());                 // generates a fresh Ulid
$holder->setCurrent($factory->create(new Ulid('...'))); // reuse an incoming id
$holder->setCurrent(null);                                // clear
```

## Bundle-driven sources

The [`traceable-bundle`](../../../Bundle/TraceableBundle/README.md) wires the common sources (HTTP request, console command, messenger envelope). Manual `setCurrent()` calls still work — useful for custom runtimes (a websocket worker, an ad-hoc CLI script).
