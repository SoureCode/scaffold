# Usage patterns

## Reading the current trace id

```php
use SoureCode\Component\Traceable\TraceStackInterface;

final class SomeLogger
{
    public function __construct(
        private readonly TraceStackInterface $traceIds,
    ) {}

    public function log(string $message): void
    {
        $traceId = $this->traceIds->getId();
        // …
    }
}
```

The provider returns `null` when no trace is active.

## Pushing and popping

```php
use SoureCode\Component\Traceable\TraceStack;
use Symfony\Component\Uid\Ulid;

$stack = new TraceStack();

$id = $stack->push(new Ulid()); // explicit id
$id = $stack->push();           // generates a fresh Ulid

// …

$stack->pop(); // returns the popped id, or null when empty
```

Nested pushes are supported — `getId()` always returns the top of the stack.

## Scoped trace

```php
$stack->withTrace(new Ulid(), function () use ($stack): void {
    // inside the callback: getId() returns the new ulid
});
// outside: previous trace id (or null) is current again
```

`withTrace(null, $callback)` generates a fresh `Ulid` for the scope. The pop happens in a `finally`, so the stack is restored even when the callback throws.

## Sources

The component does not bind to a runtime. Concrete sources (HTTP request listener, console event listener, messenger middleware, scheduler) are wired by the bundle.

Manual usage works too — application code can call `push()` / `withTrace()` directly from any handler that has a meaningful correlation id.
