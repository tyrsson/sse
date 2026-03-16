# Templates

`webware/sse` ships two Laminas View `.phtml` partial templates used by `NotificationHandler` to render notification HTML fragments before they are emitted as SSE events.

---

## Template keys

Templates are registered under the `sse` view namespace and resolved by the `Laminas\View\Helper\Partial` helper.

| Template key | Physical path | Used for |
|---|---|---|
| `sse::info` | `templates/sse/info.phtml` | Info-level notifications |
| `sse::message` | `templates/sse/mesage.phtml` | Generic message notifications |

> Additional levels (`warning`, `error`, etc.) can be added by creating `templates/sse/<level>.phtml` and registering the path via `templates.map` in your application config.

---

## Variables

All templates receive the following variables from `NotificationHandler`:

| Variable | Type | Description |
|---|---|---|
| `$level` | `string` | The message severity level (e.g. `'info'`, `'warning'`, `'error'`). Also the SSE event type. |
| `$message` | `string` | The notification text content. |

---

## Default template: `sse::message`

```php
<article class="notification message">
    <?= $this->escapeHtml($message) ?>
</article>
```

The output of this template becomes the `data:` payload of the corresponding SSE event.

---

## Overriding templates

Override a template in your application config:

```php
// In your application's ConfigProvider or config file:
return [
    'templates' => [
        'map' => [
            'sse::info'    => __DIR__ . '/../templates/my-app/sse/info.phtml',
            'sse::message' => __DIR__ . '/../templates/my-app/sse/message.phtml',
            'sse::warning' => __DIR__ . '/../templates/my-app/sse/warning.phtml',
            'sse::error'   => __DIR__ . '/../templates/my-app/sse/error.phtml',
        ],
    ],
];
```

The later map entry wins in `laminas-config-aggregator`'s merge order. Ensure your application's `ConfigProvider` is listed **after** `Webware\SSE\ConfigProvider` in the aggregator.

---

## Escaping

Always escape output with `$this->escapeHtml($message)` unless you intentionally trust the message content as HTML. `NotificationHandler` passes message strings from `SystemMessengerInterface` directly without additional sanitisation.
