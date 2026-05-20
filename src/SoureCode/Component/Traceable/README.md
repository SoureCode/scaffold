# sourecode/traceable

Trace id propagation primitive. Tracks the current `Ulid`-backed trace id for the active scope (HTTP request, console command, message handler, …).

## When to use

You want one id that follows a unit of work across logs, response headers, messenger envelopes, scheduler ticks — without each layer reinventing its own correlation id.

## When not to use

You need OpenTelemetry-style distributed tracing with spans, sampling, exporters, or a vendor backend. This is a single id, not a span tree.

## Install

Part of the `scaffold` monorepo. The [`traceable-bundle`](../../Bundle/TraceableBundle/README.md) wires sources for HTTP, console, and messenger. Without it, set the context manually.

## Minimal example

```php
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;

$factory = new TraceContextFactory();
$holder  = new TraceContextHolder();

$holder->setCurrent($factory->create()); // fresh Ulid

$traceId = $holder->getCurrent()?->getId(); // Ulid|null
```

## Reference

- [Usage patterns](docs/usage.md) — reading, setting, integration recipes.

## Public surface

| Class | Role |
|-------|------|
| `TraceContextInterface` | Contract: `getId(): Ulid`. |
| `TraceContext` | Immutable implementation. |
| `TraceContextFactory` | Builds a `TraceContext`; reuses an incoming `Ulid` or generates one. |
| `TraceContextHolder` | Per-scope holder of the current `TraceContextInterface`. |

## Composition

- HTTP — echo the trace id on a response header.
- Logging — attach it to every log line via a Monolog processor.
- Messenger — stamp envelopes on dispatch, restore on handle.

All of the above are wired by the bundle.

## Limits

- Trace ids are always `Symfony\Component\Uid\Ulid`. Other id types are out of scope.
- A holder is per-scope state; spawn a fresh one if you re-enter from a parallel context.

## Stability

The four classes above are stable.
