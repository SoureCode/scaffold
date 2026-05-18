# Traceable

Runtime-context trace id propagation. A small abstraction over "what is the current trace id?" — usable for logging, response headers, outgoing propagation, audit, and (when wired) snapshot stamping.

## Contents

- [Usage](usage.md) — `TraceStackInterface`, `TraceStack`

## Concepts

| Concept | Purpose |
|---------|---------|
| `TraceStackInterface` | Contract: `push` / `pop` / `withTrace` / `getId` |
| `TraceStack` | Default implementation |
| `Ulid` | Trace ids are always `Symfony\Component\Uid\Ulid`. |

## Source-agnostic

The component does not decide where trace ids come from — they can be:

- Pushed from an HTTP request listener (per request)
- Pushed from a CLI console event listener (per command invocation)
- Pushed from a messenger middleware (one per envelope handled)
- Pushed from a scheduler tick
- Set manually by application code

The bundle layer wires concrete sources. The component is provider-only.

## Composition

Other behaviors read `TraceStackInterface` when present:

- Logging — Monolog processor adds `trace_id` to every log line.
- HTTP — response header `X-Request-Id` echoes the current id.
- Versionable — snapshot writer stamps the current trace id on each version row (opt-in integration, not built in today).
