# Installation

## Requirements

| Requirement | Version |
|---|---|
| PHP | `~8.2 \|\| ~8.3 \|\| ~8.4 \|\| ~8.5` |
| `laminas/laminas-diactoros` | `^3.0` |
| `laminas/laminas-httphandlerrunner` | `^2.0` |
| `psr/http-message` | `^2.0` |
| `psr/http-server-handler` | `^1.0` |
| `psr/http-server-middleware` | `^1.0` |
| `psr/container` | `^2.0` |

## Composer

```bash
composer require webware/sse
```

All required packages are pulled in automatically.

## Optional: laminas-servicemanager

`laminas/laminas-servicemanager` is not required but is the recommended PSR-11
container for Mezzio applications.  When present, the bundled `ConfigProvider`
wires all services through its standard factory system.

```bash
composer require laminas/laminas-servicemanager
```

If you use a different PSR-11 container, see [Configuration](configuration.md)
for how to register services manually.

## Verifying the install

```bash
composer show webware/sse
```

You should see the package listed with its version and all dependencies
resolved.

## PHP runtime settings

SSE streams are long-lived HTTP connections.  A few PHP and web server
settings affect their behaviour:

### `output_buffering`

The `SseEmitter` drains all active output buffers with `ob_end_flush()` before
starting the stream.  It is still good practice to set `output_buffering = Off`
in your `php.ini` for SSE endpoints so that no buffer is opened in the first
place:

```ini
output_buffering = Off
```

### `max_execution_time`

`SseEmitter` calls `set_time_limit(0)` at the start of every stream, so the
default `max_execution_time` will not cut the connection.  No manual
configuration is needed.

### Nginx: disable proxy buffering

The `SseResponse` sets `X-Accel-Buffering: no` by default, which instructs
Nginx to pass bytes straight through to the client.  If you manage Nginx
configuration directly you can also set it there:

```nginx
location /events {
    proxy_buffering off;
    proxy_cache    off;
    proxy_pass     http://upstream;
}
```

### Apache: disable `mod_deflate` for event streams

Gzip compression is incompatible with streamed SSE because compressed output is
held in a buffer until it reaches a minimum size.  Either disable `mod_deflate`
globally for SSE routes or use a `SetEnvIf` directive:

```apache
<Location /events>
    SetEnv no-gzip 1
</Location>
```

---

← [Back to README](../README.md)
