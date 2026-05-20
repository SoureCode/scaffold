# sourecode/traceable-bundle

Symfony bundle wiring for [`sourecode/traceable`](../../Component/Traceable/README.md). Registers a `TraceContextHolder` that the bundle's listeners and messenger middleware mutate with a fresh `TraceContext` for each runtime scope (HTTP request, console command, messenger message).

## Install

Part of the `scaffold` monorepo — always installed with the rest. Symfony Flex registers the bundle automatically.

## Configuration

```yaml
traceable:
    http:
        enabled: true
        request_header:  'X-Request-Id'  # null disables incoming parsing (always generate)
        response_header: 'X-Request-Id'  # null disables echo on response
    console:
        enabled: true
        env_var: 'TRACE_ID'              # null disables incoming env parsing (always generate)
    messenger:
        enabled: true
```

## Sources

| Source | When | Trace id |
|--------|------|----------|
| HTTP | `kernel.request` (priority 1024) | Parsed from `request_header` if a valid Ulid, else generated. Echoed on `response_header` at `kernel.response`. Sub-requests ignored. Invalid incoming values are logged as a warning. |
| Console | `console.command` (priority 1024) | Parsed from the `env_var` (default `TRACE_ID`) if a valid Ulid, else generated. Invalid values are logged as a warning. |
| Messenger | message handle (envelope has `ReceivedStamp`) | Read from `TraceStamp` on the envelope; falls back to generated. On dispatch, the current trace id is attached as `TraceStamp` so async/scheduled work inherits it. |

## Scheduler

Symfony Scheduler dispatches scheduled tasks through the message bus. `TraceContextMiddleware` covers them transitively — each handle gets a trace id (from the stamp if a trace was active at dispatch, otherwise a fresh Ulid).

## Public services

| Service id | |
|-----------|---|
| `TraceContextFactory` | concrete factory |
| `TraceContextHolder` | mutable per-scope holder; inject this to read the current `TraceContextInterface` via `getCurrent()` |
