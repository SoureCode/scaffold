# sourecode/traceable-bundle

Symfony wiring for [`sourecode/traceable`](../../Component/Traceable/README.md). Pushes a fresh `TraceContext` into the holder for each HTTP request, console command, and messenger envelope.

## Install

Part of the `scaffold` monorepo. Symfony Flex registers the bundle automatically.

## Configuration

```yaml
traceable:
    http:
        enabled: true
        request_header:  'X-Request-Id'   # null disables incoming parsing (always generate)
        response_header: 'X-Request-Id'   # null disables echo on response
    console:
        enabled: true
        env_var: 'TRACE_ID'               # null disables incoming env parsing
    messenger:
        enabled: true
```

## Sources

| Source | When | Trace id |
|--------|------|----------|
| HTTP | Main request | Parsed from `request_header` if a valid Ulid, else generated. Echoed on `response_header` at response time. Sub-requests inherit, do not generate. |
| Console | Command start | Parsed from `env_var` if a valid Ulid, else generated. |
| Messenger | Envelope handled | Read from `TraceStamp` on the envelope; otherwise generated. Outgoing dispatches are stamped with the current id so async/scheduled work inherits it. |

Invalid incoming values (HTTP, console) are logged as a warning before falling back to a generated id.

Symfony Scheduler dispatches scheduled tasks through the message bus; messenger coverage applies transitively.

## Public surface

| Service id | Role |
|------------|------|
| `SoureCode\Component\Traceable\TraceContextFactory` | Build a `TraceContext` from an optional incoming `Ulid`. |
| `SoureCode\Component\Traceable\TraceContextHolder` | Read the current `TraceContextInterface` via `getCurrent()`. |

## Behavior and limits

See the [component README](../../Component/Traceable/README.md).
