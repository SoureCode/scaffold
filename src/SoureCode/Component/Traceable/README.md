# sourecode/traceable

Framework-agnostic trace id propagation primitive. Tracks the *current* `Ulid`-backed trace id for the active scope (HTTP request, console command, message handler, …).

## Install

Part of the `scaffold` monorepo — always installed with the rest. See the root [README](../../../../README.md).

## Public surface

| Class | Role |
|-------|------|
| `TraceContextInterface` | Contract: `getId(): Ulid`. |
| `TraceContext` | Immutable implementation. |
| `TraceContextFactory` | Builds `TraceContext`; reuses an incoming `Ulid` or generates a fresh one. |
| `TraceContextHolder` | Mutable per-scope holder of the current `TraceContextInterface`. |

The component is provider-only — it does not bind to a runtime. The companion [`sourecode/traceable-bundle`](../../Bundle/TraceableBundle/README.md) wires concrete sources (HTTP listener, console listener, messenger middleware) that push contexts into the holder.

## Quick start

```php
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Uid\Ulid;

$factory = new TraceContextFactory();
$holder = new TraceContextHolder();

$holder->setCurrent($factory->create());                 // fresh Ulid
$holder->setCurrent($factory->create(new Ulid('...'))); // explicit id

$current = $holder->getCurrent();                        // ?TraceContextInterface
$traceId = $current?->getId();                           // ?Ulid
```

See [docs/index.md](docs/index.md) and [docs/usage.md](docs/usage.md) for more.
