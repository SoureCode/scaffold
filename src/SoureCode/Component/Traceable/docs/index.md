# Traceable

Runtime-context trace id propagation. A small abstraction over "what is the current trace id?" — usable for logging, response headers, outgoing propagation, audit.

## Contents

- [Usage](usage.md) — `TraceContext`, `TraceContextFactory`, `TraceContextHolder`

## Concepts

| Concept | Purpose |
|---------|---------|
| `TraceContextInterface` | Contract: `getId(): Ulid`. |
| `TraceContext` | Default implementation. Immutable; wraps one `Ulid`. |
| `TraceContextFactory` | Builds a `TraceContext` from an optional incoming `Ulid` (generates a fresh one when none is provided). |
| `TraceContextHolder` | Mutable per-scope holder for the *current* `TraceContextInterface`. The bundle's listeners and messenger middleware push/clear here; consumers read it via `getCurrent()`. |
| `Ulid` | Trace ids are always `Symfony\Component\Uid\Ulid`. |

## Source-agnostic

The component does not decide where trace ids come from — they can be:

- Set from an HTTP request listener (per request)
- Set from a CLI console event listener (per command invocation)
- Set from a messenger middleware (one per envelope handled)
- Set from a scheduler tick
- Set manually by application code

The bundle layer wires concrete sources. The component is provider-only.

## Composition

Other behaviors read `TraceContextHolder::getCurrent()` when present:

- Logging — a Monolog processor can add `trace_id` to every log line.
- HTTP — response header (default `X-Request-Id`) echoes the current id.
- Messenger — outgoing envelopes are stamped with the current id; received envelopes restore it.
